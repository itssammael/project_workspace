<?php

namespace App\Services;

use App\Models\User;
use App\Models\SystemRule;
use App\Models\TaskBoard;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SystemRuleEvaluator
{
    /**
     * Evaluate if a user is permitted for a specific operation rule defined in System Settings.
     */
    public static function checkOperation(User $user, string $operation, array $defaultAllowedSystemRoles = ['admin'], array $defaultAllowedFunctionalRoles = []): bool
    {
        if (!Schema::hasTable('system_rules')) {
            return self::evaluateUserRoles($user, $defaultAllowedSystemRoles, $defaultAllowedFunctionalRoles);
        }

        $rule = SystemRule::where('enabled', true)
            ->get()
            ->first(function ($r) use ($operation) {
                return ($r->rule_logic['operation'] ?? null) === $operation;
            });

        if (!$rule) {
            return self::evaluateUserRoles($user, $defaultAllowedSystemRoles, $defaultAllowedFunctionalRoles);
        }

        $logic = $rule->rule_logic ?? [];
        $allowedSystemRoles = $logic['allowed_system_roles'] ?? $defaultAllowedSystemRoles;
        $allowedFunctionalRoles = $logic['allowed_functional_roles'] ?? $defaultAllowedFunctionalRoles;

        // If approval_chain is configured, parse step role names into system/functional role slugs
        if (!empty($logic['approval_chain']) && is_array($logic['approval_chain'])) {
            foreach ($logic['approval_chain'] as $chainItem) {
                $slug = Str::slug(trim($chainItem), '_');
                if (in_array($slug, ['administrator', 'admin'])) {
                    if (!in_array('admin', $allowedSystemRoles)) {
                        $allowedSystemRoles[] = 'admin';
                    }
                } elseif ($slug === 'user') {
                    if (!in_array('user', $allowedSystemRoles)) {
                        $allowedSystemRoles[] = 'user';
                    }
                } elseif ($slug === 'viewer') {
                    if (!in_array('viewer', $allowedSystemRoles)) {
                        $allowedSystemRoles[] = 'viewer';
                    }
                } else {
                    if (!in_array($slug, $allowedFunctionalRoles)) {
                        $allowedFunctionalRoles[] = $slug;
                    }
                }
            }
        }

        return self::evaluateUserRoles($user, $allowedSystemRoles, $allowedFunctionalRoles);
    }

    /**
     * Evaluate if a user is permitted to view a specific target route based on page_access_rule in System Settings.
     */
    public static function checkPageAccess(User $user, string $targetRoute, array $defaultAllowedSystemRoles = ['admin'], array $defaultAllowedFunctionalRoles = ['admin_staff']): bool
    {
        if (!Schema::hasTable('system_rules')) {
            return self::evaluateUserRoles($user, $defaultAllowedSystemRoles, $defaultAllowedFunctionalRoles);
        }

        $normalizedTarget = '/' . ltrim(trim($targetRoute), '/');

        $rule = SystemRule::where('enabled', true)
            ->where('type', 'page_access_rule')
            ->get()
            ->first(function ($r) use ($normalizedTarget) {
                $rawTarget = $r->rule_logic['target_route'] ?? null;
                if (!$rawTarget) {
                    return false;
                }
                $t = '/' . ltrim(trim($rawTarget), '/');
                return $t === $normalizedTarget || str_starts_with($normalizedTarget, rtrim($t, '/') . '/');
            });

        if (!$rule) {
            return self::evaluateUserRoles($user, $defaultAllowedSystemRoles, $defaultAllowedFunctionalRoles);
        }

        $logic = $rule->rule_logic ?? [];
        $allowedSystemRoles = $logic['allowed_system_roles'] ?? $defaultAllowedSystemRoles;
        $allowedFunctionalRoles = $logic['allowed_functional_roles'] ?? $defaultAllowedFunctionalRoles;

        $hasRoleAccess = self::evaluateUserRoles($user, $allowedSystemRoles, $allowedFunctionalRoles);
        if (!$hasRoleAccess) {
            return false;
        }

        if (!empty($logic['allowed_employee_types'])) {
            $userEmployeeType = $user->member?->employeeType?->description;
            if (!$userEmployeeType || !in_array($userEmployeeType, $logic['allowed_employee_types'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a workflow type or workflow name matches a protected workflow type (e.g. Kanban or IPCR).
     */
    public static function isProtectedWorkflowType(string $typeName): bool
    {
        if (!Schema::hasTable('system_rules')) {
            return in_array(strtolower(trim($typeName)), ['kanban', 'ipcr']);
        }

        $rule = SystemRule::where('enabled', true)
            ->get()
            ->first(function ($r) {
                return ($r->rule_logic['operation'] ?? null) === 'manage_protected_workflow_types';
            });

        if (!$rule) {
            return in_array(strtolower(trim($typeName)), ['kanban', 'ipcr']);
        }

        $protectedTypes = $rule->rule_logic['protected_workflow_types'] ?? ['Kanban', 'IPCR'];
        $protectedSlugs = array_map(fn($t) => strtolower(trim($t)), $protectedTypes);

        return in_array(strtolower(trim($typeName)), $protectedSlugs);
    }

    /**
     * Evaluate user system and functional roles against lists of allowed roles.
     */
    public static function evaluateUserRoles(User $user, array $allowedSystemRoles, array $allowedFunctionalRoles): bool
    {
        $userSystemRole = $user->role?->slug;
        $userFunctionalRoles = $user->member ? $user->member->memberRoles->pluck('slug')->toArray() : [];

        $hasSystemAccess = !empty($allowedSystemRoles) && in_array($userSystemRole, $allowedSystemRoles);
        $hasFunctionalAccess = !empty($allowedFunctionalRoles) && count(array_intersect($userFunctionalRoles, $allowedFunctionalRoles)) > 0;

        return $hasSystemAccess || $hasFunctionalAccess;
    }

    /**
     * Get all Department IDs that a user belongs to (via Sections or Department Head role).
     */
    public static function getUserDepartmentIds(User $user): array
    {
        if (!$user || !$user->member) {
            return [];
        }

        $member = $user->member;
        $deptIds = [];

        // 1. Department IDs from sections member belongs to
        $sectionDeptIds = $member->sections()->pluck('department_id')->filter()->toArray();
        $deptIds = array_merge($deptIds, $sectionDeptIds);

        // 2. Department IDs from sections where member is Project Manager
        $pmDeptIds = \App\Models\Section::where('member_id', $member->id)->pluck('department_id')->filter()->toArray();
        $deptIds = array_merge($deptIds, $pmDeptIds);

        // 3. Department IDs where member is Department Head
        $headDeptIds = \App\Models\Department::where('department_head_id', $member->id)->pluck('id')->filter()->toArray();
        $deptIds = array_merge($deptIds, $headDeptIds);

        return array_values(array_unique(array_filter($deptIds)));
    }

    /**
     * Apply Same-Department Member Access Restriction rule to a Member query.
     */
    public static function scopeMemberQueryByDepartmentRule($query, User $user)
    {
        if (!Schema::hasTable('system_rules')) {
            return $query;
        }

        $rule = SystemRule::where('enabled', true)
            ->get()
            ->first(function ($r) {
                $op = $r->rule_logic['operation'] ?? null;
                return $op === 'same_department_member_access' || $op === 'restrict_member_access_to_same_department';
            });

        if (!$rule) {
            return $query;
        }

        $logic = $rule->rule_logic ?? [];
        $bypassRoles = $logic['bypass_system_roles'] ?? ['admin'];

        if ($user->role && in_array($user->role->slug, $bypassRoles)) {
            return $query;
        }

        $deptIds = self::getUserDepartmentIds($user);

        return $query->where(function ($q) use ($deptIds) {
            if (empty($deptIds)) {
                $q->whereRaw('1 = 0');
                return;
            }

            $q->whereHas('sections', function ($sq) use ($deptIds) {
                $sq->whereIn('department_id', $deptIds);
            })
            ->orWhereHas('headedDepartments', function ($dq) use ($deptIds) {
                $dq->whereIn('id', $deptIds);
            })
            ->orWhereIn('id', function ($sub) use ($deptIds) {
                $sub->select('member_id')
                    ->from('sections')
                    ->whereIn('department_id', $deptIds)
                    ->whereNotNull('member_id');
            });
        });
    }

    /**
     * Apply Same-Department Member Access Restriction rule to a User query.
     */
    public static function scopeUserQueryByDepartmentRule($query, User $user)
    {
        if (!Schema::hasTable('system_rules')) {
            return $query;
        }

        $rule = SystemRule::where('enabled', true)
            ->get()
            ->first(function ($r) {
                $op = $r->rule_logic['operation'] ?? null;
                return $op === 'same_department_member_access' || $op === 'restrict_member_access_to_same_department';
            });

        if (!$rule) {
            return $query;
        }

        $logic = $rule->rule_logic ?? [];
        $bypassRoles = $logic['bypass_system_roles'] ?? ['admin'];

        if ($user->role && in_array($user->role->slug, $bypassRoles)) {
            return $query;
        }

        $deptIds = self::getUserDepartmentIds($user);

        return $query->where(function ($q) use ($deptIds) {
            if (empty($deptIds)) {
                $q->whereRaw('1 = 0');
                return;
            }

            $q->whereHas('member', function ($mq) use ($deptIds) {
                $mq->whereHas('sections', function ($sq) use ($deptIds) {
                    $sq->whereIn('department_id', $deptIds);
                })
                ->orWhereHas('headedDepartments', function ($dq) use ($deptIds) {
                    $dq->whereIn('id', $deptIds);
                })
                ->orWhereIn('id', function ($sub) use ($deptIds) {
                    $sub->select('member_id')
                        ->from('sections')
                        ->whereIn('department_id', $deptIds)
                        ->whereNotNull('member_id');
                });
            });
        });
    }

    /**
     * Check if a user can access a specific target Member.
     */
    public static function canUserAccessMember(User $user, \App\Models\Member $targetMember): bool
    {
        if (!Schema::hasTable('system_rules')) {
            return true;
        }

        $rule = SystemRule::where('enabled', true)
            ->get()
            ->first(function ($r) {
                $op = $r->rule_logic['operation'] ?? null;
                return $op === 'same_department_member_access' || $op === 'restrict_member_access_to_same_department';
            });

        if (!$rule) {
            return true;
        }

        $logic = $rule->rule_logic ?? [];
        $bypassRoles = $logic['bypass_system_roles'] ?? ['admin'];

        if ($user->role && in_array($user->role->slug, $bypassRoles)) {
            return true;
        }

        $userDeptIds = self::getUserDepartmentIds($user);
        $targetUser = $targetMember->user ?? User::find($targetMember->user_id);
        if (!$targetUser) {
            return false;
        }
        $targetDeptIds = self::getUserDepartmentIds($targetUser);

        return count(array_intersect($userDeptIds, $targetDeptIds)) > 0;
    }

    /**
     * Check if a user is permitted to edit and update tab data in Support Function Reports.
     * Enforces rule: only Members under Section "Admin" (or System Admin) can edit.
     */
    public static function canEditSupportFunctionReports(User $user): bool
    {
        if (!Schema::hasTable('system_rules')) {
            return true;
        }

        $rule = SystemRule::where('enabled', true)
            ->get()
            ->first(function ($r) {
                $op = $r->rule_logic['operation'] ?? null;
                return $op === 'edit_support_function_reports' || $op === 'restrict_support_function_reports_edit';
            });

        if (!$rule) {
            return true;
        }

        $logic = $rule->rule_logic ?? [];
        $bypassRoles = $logic['bypass_system_roles'] ?? ['admin'];

        if ($user->role && in_array($user->role->slug, $bypassRoles)) {
            return true;
        }

        if (!$user->member) {
            return false;
        }

        $allowedSections = array_map('strtolower', $logic['allowed_sections'] ?? ['Admin']);
        $userSections = $user->member->sections()->pluck('name')->toArray();

        foreach ($userSections as $secName) {
            if (in_array(strtolower(trim($secName)), $allowedSections)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user is permitted to create, update, or delete tasks within a Task Board Workflow.
     * Enforces rule: Tasks can only be created/managed in TaskBoard Workflow if the user is a collaborator
     * or assigned member of the taskboard, and has the required System & Functional roles.
     */
    public static function canManageTasks(User $user, TaskBoard $taskBoard): bool
    {
        // 1. System Administrators always have full management authority
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->member) {
            return false;
        }

        // Rule constraint: user MUST be a collaborator or assigned member of the taskboard
        $isBoardMemberOrCollaborator = $taskBoard->members()->where('members.id', $user->member->id)->exists();
        if (!$isBoardMemberOrCollaborator) {
            return false;
        }

        if (!Schema::hasTable('system_rules')) {
            return self::isTaskBoardProjectManager($user, $taskBoard);
        }

        // 2. Fetch System Rule governing task creation / management in Task Board Workflows
        $taskBoardRule = SystemRule::where(function ($q) {
                $q->where('name', 'like', '%Task Creation%')
                  ->orWhere('name', 'like', '%Task Board Workflow%')
                  ->orWhere('rule_logic->target', 'task_board_workflows');
            })
            ->latest('id')
            ->first();

        if ($taskBoardRule) {
            if (!$taskBoardRule->enabled) {
                return false;
            }

            $logic = $taskBoardRule->rule_logic ?? [];
            $allowedSystemRoles = $logic['allowed_system_roles'] ?? ['admin', 'user'];
            $allowedFunctionalRoles = $logic['allowed_functional_roles'] ?? ['project_manager'];

            $userSystemRole = $user->role?->slug;
            $userFunctionalRoles = $user->member ? $user->member->memberRoles->pluck('slug')->toArray() : [];

            $systemRoleAllowed = empty($allowedSystemRoles) || in_array($userSystemRole, $allowedSystemRoles);

            // Check if user has required functional role globally
            $hasFunctionalRoleGlobally = !empty($allowedFunctionalRoles) && count(array_intersect($userFunctionalRoles, $allowedFunctionalRoles)) > 0;

            // Check if user has required functional role specifically assigned on this task board
            $boardRoleSlugs = DB::table('task_board_members')
                ->join('member_roles', 'task_board_members.member_role_id', '=', 'member_roles.id')
                ->where('task_board_members.task_board_id', $taskBoard->id)
                ->where('task_board_members.member_id', $user->member->id)
                ->pluck('member_roles.slug')
                ->toArray();
            $hasBoardFunctionalRole = count(array_intersect($boardRoleSlugs, $allowedFunctionalRoles)) > 0;

            if (!$systemRoleAllowed || (!$hasFunctionalRoleGlobally && !$hasBoardFunctionalRole)) {
                return false;
            }

            return self::isTaskBoardProjectManager($user, $taskBoard);
        }

        // 3. Fallback: check general manage_tasks operation rule
        $isPermittedRole = self::checkOperation($user, 'manage_tasks', ['admin'], ['project_manager']);
        if (!$isPermittedRole) {
            return false;
        }

        return self::isTaskBoardProjectManager($user, $taskBoard);
    }

    /**
     * Check if a user is a Project Manager for a specific Task Board or its creator.
     */
    public static function isTaskBoardProjectManager(User $user, TaskBoard $taskBoard): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $member = $user->member;
        if (!$member) {
            return false;
        }

        // Must be a collaborator or assigned member of the taskboard
        $isBoardMember = $taskBoard->members()->where('members.id', $member->id)->exists();
        if (!$isBoardMember) {
            return false;
        }

        // 1. Assigned as Project Manager directly on the Task Board itself in task_board_members
        $isBoardPM = DB::table('task_board_members')
            ->join('member_roles', 'task_board_members.member_role_id', '=', 'member_roles.id')
            ->where('task_board_members.task_board_id', $taskBoard->id)
            ->where('task_board_members.member_id', $member->id)
            ->where('member_roles.slug', 'project_manager')
            ->exists();

        if ($isBoardPM) {
            return true;
        }

        // 2. Has global functional role 'project_manager' and is on the board
        return $member->memberRoles()->where('slug', 'project_manager')->exists();
    }

    /**
     * Check if a user is permitted to view a Task Board according to the Task Board Membership & Visibility Guard rule.
     * Enforces:
     * - System Role: "Administrator" and Functional Role: "Department Head" retain full visibility.
     * - Functional Role: "Section Head" can view all Task Boards in the sections they belong to.
     * - All other Users can only view Task Boards where they are a member or a collaborator.
     */
    public static function canViewTaskBoard(User $user, TaskBoard $taskBoard): bool
    {
        // 1. Administrator capability is unaffected
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->member) {
            return false;
        }

        if (!Schema::hasTable('system_rules')) {
            return $taskBoard->members()->where('members.id', $user->member->id)->exists();
        }

        // 2. Department Head capability is unaffected
        $isDeptHead = $user->member->memberRoles()
            ->whereIn('slug', ['department_head', 'department-head'])
            ->exists();

        if ($isDeptHead) {
            $userDeptIds = self::getUserDepartmentIds($user);
            if (empty($userDeptIds)) {
                return true;
            }
            $boardDeptId = $taskBoard->section?->department_id;
            return !$boardDeptId || in_array($boardDeptId, $userDeptIds);
        }

        // 3. Exception: Functional role "Section Head" can view all Task boards in the section they belong
        $isSectionHead = $user->member->memberRoles()
            ->whereIn('slug', ['section-head', 'section_head'])
            ->exists();

        if ($isSectionHead) {
            $userSectionIds = $user->member->sections()->pluck('sections.id')->toArray();
            $managedSectionIds = \App\Models\Section::where('member_id', $user->member->id)->pluck('id')->toArray();
            $allSectionIds = array_unique(array_merge($userSectionIds, $managedSectionIds));

            if ($taskBoard->section_id && in_array($taskBoard->section_id, $allSectionIds)) {
                return true;
            }
        }

        // 4. Default for All Users: can only view Task boards where they are a member or a collaborator
        return $taskBoard->members()->where('members.id', $user->member->id)->exists();
    }

    /**
     * Scope a TaskBoard query according to the Task Board Membership & Visibility Guard rule.
     */
    public static function scopeTaskBoardQuery($query, User $user)
    {
        // 1. Administrator capability is unaffected
        if ($user->hasRole('admin')) {
            return $query;
        }

        if (!$user->member) {
            return $query->whereRaw('1 = 0');
        }

        if (!Schema::hasTable('system_rules')) {
            return $query->whereHas('members', fn($mq) => $mq->where('members.id', $user->member->id));
        }

        // 2. Department Head capability is unaffected
        $isDeptHead = $user->member->memberRoles()
            ->whereIn('slug', ['department_head', 'department-head'])
            ->exists();

        if ($isDeptHead) {
            $userDeptIds = self::getUserDepartmentIds($user);
            if (empty($userDeptIds)) {
                return $query;
            }
            return $query->where(function ($q) use ($userDeptIds, $user) {
                $q->whereHas('section', function ($sq) use ($userDeptIds) {
                    $sq->whereIn('department_id', $userDeptIds);
                })->orWhereHas('members', fn($mq) => $mq->where('members.id', $user->member->id));
            });
        }

        // 3. Exception: Functional role "Section Head" can view all Task boards in the section they belong
        $isSectionHead = $user->member->memberRoles()
            ->whereIn('slug', ['section-head', 'section_head'])
            ->exists();

        if ($isSectionHead) {
            $userSectionIds = $user->member->sections()->pluck('sections.id')->toArray();
            $managedSectionIds = \App\Models\Section::where('member_id', $user->member->id)->pluck('id')->toArray();
            $allSectionIds = array_unique(array_merge($userSectionIds, $managedSectionIds));

            return $query->where(function ($q) use ($allSectionIds, $user) {
                if (!empty($allSectionIds)) {
                    $q->whereIn('section_id', $allSectionIds);
                }
                $q->orWhereHas('members', function ($mq) use ($user) {
                    $mq->where('members.id', $user->member->id);
                });
            });
        }

        // 4. Default for All Users: can only view Task boards where they are a member or a collaborator
        return $query->whereHas('members', function ($mq) use ($user) {
            $mq->where('members.id', $user->member->id);
        });
    }
}
