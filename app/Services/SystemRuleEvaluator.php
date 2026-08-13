<?php

namespace App\Services;

use App\Models\User;
use App\Models\SystemRule;
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
}
