<?php

namespace App\Services;

use App\Models\User;
use App\Models\Section;
use App\Models\Department;
use Illuminate\Http\Request;

class DashboardRoleService
{
    /**
     * View identifiers supported by the dashboard system.
     */
    public const VIEW_STAFF = 'staff';
    public const VIEW_SUPERVISOR = 'supervisor';
    public const VIEW_DEPARTMENT_HEAD = 'department_head';
    public const VIEW_ADMIN = 'admin';

    /**
     * Get the list of views that the user is authorized to access.
     *
     * @param User $user
     * @return array<string>
     */
    public static function getAuthorizedViews(User $user): array
    {
        $views = [];
        $member = $user->member;
        $memberRoles = $member ? $member->memberRoles->pluck('slug')->toArray() : [];

        // 1. Admin View: User with system role 'admin' or explicit admin management page access
        if ($user->hasRole('admin') || SystemRuleEvaluator::checkPageAccess($user, '/admin/management', ['admin'], [])) {
            $views[] = self::VIEW_ADMIN;
        }

        // 2. Department Head View: System Admin, Member with 'department_head' role, or assigned Department Head in departments table
        $isDeptHead = $user->hasRole('admin')
            || in_array('department_head', $memberRoles)
            || in_array('department-head', $memberRoles)
            || ($member && Department::where('department_head_id', $member->id)->exists());

        if ($isDeptHead) {
            $views[] = self::VIEW_DEPARTMENT_HEAD;
        }

        // 3. Supervisor View: System Admin, Department Head, Section Head, Section Project Manager, or Project Manager with supervisory access
        $isSupervisor = $isDeptHead
            || in_array('section-head', $memberRoles)
            || in_array('section_head', $memberRoles)
            || ($member && Section::where('member_id', $member->id)->exists())
            || (in_array('project_manager', $memberRoles) && $member && $member->sections()->exists());

        if ($isSupervisor) {
            $views[] = self::VIEW_SUPERVISOR;
        }

        // 4. Staff View: Available to all users to see personal action items, assigned tasks, and active work
        $views[] = self::VIEW_STAFF;

        return array_values(array_unique($views));
    }

    /**
     * View definition metadata.
     */
    public static function getViewDefinitions(): array
    {
        return [
            self::VIEW_STAFF => [
                'key' => self::VIEW_STAFF,
                'name' => 'Staff View',
                'badge' => 'Staff',
                'description' => 'Personal action items, active timer, recent activity, and assigned tasks',
                'icon' => 'user-check',
            ],
            self::VIEW_SUPERVISOR => [
                'key' => self::VIEW_SUPERVISOR,
                'name' => 'Supervisor View',
                'badge' => 'Supervisor',
                'description' => 'Team workload, approvals, section milestones, and escalations',
                'icon' => 'users-round',
            ],
            self::VIEW_DEPARTMENT_HEAD => [
                'key' => self::VIEW_DEPARTMENT_HEAD,
                'name' => 'Department Head View',
                'badge' => 'Dept Head',
                'description' => 'Executive summary, section performance, roadmap, and reports',
                'icon' => 'briefcase',
            ],
            self::VIEW_ADMIN => [
                'key' => self::VIEW_ADMIN,
                'name' => 'Administrator View',
                'badge' => 'Admin',
                'description' => 'System health, user administration, security audit logs, and configuration',
                'icon' => 'shield-check',
            ],
        ];
    }

    /**
     * Determine the effective active view for the current request, enforcing authorization.
     */
    public static function getActiveView(Request $request, User $user): string
    {
        $authorizedViews = self::getAuthorizedViews($user);

        // Check if explicitly requested in query parameter
        $requestedView = $request->query('view');

        // Check if previously stored in session
        if (!$requestedView && $request->hasSession()) {
            $requestedView = $request->session()->get('dashboard_active_view');
        }

        // If requested view is valid and authorized, use and persist it
        if ($requestedView && in_array($requestedView, $authorizedViews, true)) {
            if ($request->hasSession()) {
                $request->session()->put('dashboard_active_view', $requestedView);
            }
            return $requestedView;
        }

        // Default hierarchy: highest available authorized view
        $priorityOrder = [
            self::VIEW_ADMIN,
            self::VIEW_DEPARTMENT_HEAD,
            self::VIEW_SUPERVISOR,
            self::VIEW_STAFF,
        ];

        foreach ($priorityOrder as $candidate) {
            if (in_array($candidate, $authorizedViews, true)) {
                if ($request->hasSession()) {
                    $request->session()->put('dashboard_active_view', $candidate);
                }
                return $candidate;
            }
        }

        return self::VIEW_STAFF;
    }

    /**
     * Get available views with full metadata for UI rendering.
     */
    public static function getAvailableViewsWithMeta(User $user): array
    {
        $authorized = self::getAuthorizedViews($user);
        $defs = self::getViewDefinitions();

        $result = [];
        foreach ($authorized as $viewKey) {
            if (isset($defs[$viewKey])) {
                $result[] = $defs[$viewKey];
            }
        }

        return $result;
    }

    /**
     * Get user functional title and organizational details.
     */
    public static function getUserOrganizationalInfo(User $user): array
    {
        $member = $user->member;
        $functionalRoleNames = $member ? $member->memberRoles->pluck('name')->toArray() : [];
        $sections = $member ? $member->sections->pluck('name')->toArray() : [];
        $departments = SystemRuleEvaluator::getUserDepartmentIds($user);
        $deptNames = Department::whereIn('id', $departments)->pluck('name')->toArray();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'system_role' => $user->role?->name ?? 'User',
            'functional_roles' => $functionalRoleNames,
            'functional_roles_string' => !empty($functionalRoleNames) ? implode(' · ', $functionalRoleNames) : 'Team Member',
            'sections' => $sections,
            'departments' => $deptNames,
        ];
    }

    /**
     * Determine if the user is permitted to see the Role Access Switcher menu on the header.
     */
    public static function canViewRoleSwitcher(User $user): bool
    {
        if ($user->role && array_key_exists('show_role_switcher', $user->role->getAttributes())) {
            $roleEnabled = (bool) $user->role->show_role_switcher;
            if (!$roleEnabled && !$user->hasPermission('view.role_switcher')) {
                return false;
            }
            return $roleEnabled || $user->hasPermission('view.role_switcher');
        }

        return true;
    }
}
