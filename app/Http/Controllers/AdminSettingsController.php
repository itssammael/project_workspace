<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SystemRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    /**
     * Display the admin settings panel.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('view-admin-settings');

        $settings = [
            'system_name' => Setting::get('system_name', 'Project Tracker'),
            'theme' => Setting::get('theme', 'corporate_teal'),
            'logo' => Setting::get('logo', null),
        ];

        $systemRules = SystemRule::orderBy('created_at', 'desc')->get();

        $roles = \App\Models\Role::with('permissions')->withCount('users')->orderBy('id', 'asc')->get();
        $memberRoles = \App\Models\MemberRole::all();

        $availablePermissions = [
            [
                'key' => 'system.config',
                'name' => 'System Configuration',
                'category' => 'Administration & Security',
                'description' => 'Configure global system settings, themes, and organization preferences'
            ],
            [
                'key' => 'user.provision',
                'name' => 'User Provisioning',
                'category' => 'Administration & Security',
                'description' => 'Create, modify, and provision user accounts and system access'
            ],
            [
                'key' => 'view.audit_logs',
                'name' => 'View Audit Logs',
                'category' => 'Administration & Security',
                'description' => 'Inspect security events, login attempts, and operational audit trail'
            ],
            [
                'key' => 'manage.system_settings',
                'name' => 'Manage System Rules',
                'category' => 'Administration & Security',
                'description' => 'Create, configure, and toggle automated system rules & guards'
            ],
            [
                'key' => 'view.dashboard',
                'name' => 'View Dashboard',
                'category' => 'Dashboard & Workspaces',
                'description' => 'Access operational landing page and dashboard views'
            ],
            [
                'key' => 'view.role_switcher',
                'name' => 'Header Role Switcher Menu',
                'category' => 'Dashboard & Workspaces',
                'description' => 'Show the Role access switcher dropdown menu on the top header navigation'
            ],
            [
                'key' => 'view.all_task_boards',
                'name' => 'View All Task Boards',
                'category' => 'Dashboard & Workspaces',
                'description' => 'Inspect task boards across all sections and departments'
            ],
            [
                'key' => 'export.reports',
                'name' => 'Export Reports',
                'category' => 'Dashboard & Workspaces',
                'description' => 'Download and export operational reports (CSV / print format)'
            ],
            [
                'key' => 'edit.tasks',
                'name' => 'Edit Tasks',
                'category' => 'Tasks & Workflows',
                'description' => 'Update status and submit deliverables for assigned tasks'
            ],
            [
                'key' => 'manage.tasks',
                'name' => 'Manage Tasks',
                'category' => 'Tasks & Workflows',
                'description' => 'Create, edit, and reallocate tasks within task boards'
            ],
            [
                'key' => 'approve.tasks',
                'name' => 'Approve Submissions',
                'category' => 'Tasks & Workflows',
                'description' => 'Review, verify, and approve deliverables submitted by team members'
            ],
            [
                'key' => 'delete.tasks',
                'name' => 'Delete Tasks',
                'category' => 'Tasks & Workflows',
                'description' => 'Remove tasks and subtasks from workflows'
            ],
            [
                'key' => 'view.reports',
                'name' => 'View Support Reports',
                'category' => 'Reports & Activities',
                'description' => 'View attendance, undertime, and activity support reports'
            ],
            [
                'key' => 'edit.reports',
                'name' => 'Edit Support Reports',
                'category' => 'Reports & Activities',
                'description' => 'Submit and modify support function records and attendance'
            ],
        ];

        return Inertia::render('Admin/Settings', compact('settings', 'systemRules', 'roles', 'memberRoles', 'availablePermissions'));
    }

    /**
     * Update the general system settings.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $validated = $request->validate([
            'system_name' => 'required|string|max:255',
            'theme' => 'required|in:refined_indigo,corporate_teal,modern_midnight',
            'logo' => 'nullable|image|max:2048', // max 2MB
            'remove_logo' => 'nullable|boolean',
        ]);

        Setting::set('system_name', $validated['system_name']);
        Setting::set('theme', $validated['theme']);

        if (!empty($validated['remove_logo'])) {
            Setting::set('logo', null);
        } elseif ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $data = base64_encode(file_get_contents($file->getRealPath()));
            $mime = $file->getMimeType();
            $logoData = "data:{$mime};base64,{$data}";
            Setting::set('logo', $logoData);
        }

        \App\Models\SystemLog::log('Update Settings', "System settings updated (System Name: {$validated['system_name']}, Theme: {$validated['theme']}).");

        return redirect()->back()->with('success', 'System settings updated successfully.');
    }

    /**
     * Store a new system rule.
     */
    public function storeRule(Request $request): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:page_access_rule,role_permission_rule,conditional_logic,validation_rule,approval_rule,notification_rule,compliance_rule,data_integrity_rule',
            'enabled' => 'boolean',
            'status' => 'required|string|in:active,inactive,draft',
            'description' => 'nullable|string',
            'rule_logic' => 'nullable|array',
            'scope' => 'nullable|array',
            'actions' => 'nullable|array',
        ]);

        $userName = $request->user()?->name ?? 'System Admin';

        $rule = SystemRule::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'enabled' => $validated['enabled'] ?? true,
            'status' => $validated['status'],
            'description' => $validated['description'] ?? null,
            'rule_logic' => $validated['rule_logic'] ?? [],
            'scope' => $validated['scope'] ?? [],
            'actions' => $validated['actions'] ?? [],
            'created_by' => $userName,
            'last_modified_by' => $userName,
        ]);

        \App\Models\SystemLog::log('Create System Rule', "System rule '{$rule->name}' created.");

        return redirect()->back()->with('success', "System rule '{$rule->name}' created successfully.");
    }

    /**
     * Update an existing system rule.
     */
    public function updateRule(Request $request, SystemRule $rule): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:page_access_rule,role_permission_rule,conditional_logic,validation_rule,approval_rule,notification_rule,compliance_rule,data_integrity_rule',
            'enabled' => 'boolean',
            'status' => 'required|string|in:active,inactive,draft',
            'description' => 'nullable|string',
            'rule_logic' => 'nullable|array',
            'scope' => 'nullable|array',
            'actions' => 'nullable|array',
            'reason_for_change' => 'nullable|string',
        ]);

        $userName = $request->user()?->name ?? 'System Admin';

        $rule->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'enabled' => $validated['enabled'] ?? true,
            'status' => $validated['status'],
            'description' => $validated['description'] ?? null,
            'rule_logic' => $validated['rule_logic'] ?? [],
            'scope' => $validated['scope'] ?? [],
            'actions' => $validated['actions'] ?? [],
            'last_modified_by' => $userName,
        ]);

        $reasonLog = !empty($validated['reason_for_change']) ? " (Reason: {$validated['reason_for_change']})" : '';
        \App\Models\SystemLog::log('Update System Rule', "System rule '{$rule->name}' updated{$reasonLog}.");

        return redirect()->back()->with('success', "System rule '{$rule->name}' updated successfully.");
    }

    /**
     * Toggle a system rule enabled status.
     */
    public function toggleRule(Request $request, SystemRule $rule): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $rule->enabled = !$rule->enabled;
        $rule->status = $rule->enabled ? 'active' : 'inactive';
        $rule->last_modified_by = $request->user()?->name ?? 'System Admin';
        $rule->save();

        $statusText = $rule->enabled ? 'enabled' : 'disabled';
        \App\Models\SystemLog::log('Toggle System Rule', "System rule '{$rule->name}' was {$statusText}.");

        return redirect()->back()->with('success', "System rule '{$rule->name}' {$statusText}.");
    }

    /**
     * Clone a system rule.
     */
    public function cloneRule(Request $request, SystemRule $rule): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $userName = $request->user()?->name ?? 'System Admin';

        $newRule = SystemRule::create([
            'name' => $rule->name . ' (Copy)',
            'type' => $rule->type,
            'enabled' => false,
            'status' => 'draft',
            'description' => $rule->description,
            'rule_logic' => $rule->rule_logic,
            'scope' => $rule->scope,
            'actions' => $rule->actions,
            'created_by' => $userName,
            'last_modified_by' => $userName,
        ]);

        \App\Models\SystemLog::log('Clone System Rule', "System rule '{$rule->name}' cloned as '{$newRule->name}'.");

        return redirect()->back()->with('success', "System rule cloned as '{$newRule->name}'.");
    }

    /**
     * Destroy a system rule.
     */
    public function destroyRule(Request $request, SystemRule $rule): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $name = $rule->name;
        $rule->delete();

        \App\Models\SystemLog::log('Delete System Rule', "System rule '{$name}' was deleted.");

        return redirect()->back()->with('success', "System rule '{$name}' deleted successfully.");
    }

    /**
     * Import system rules from JSON payload.
     */
    public function importRules(Request $request): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $validated = $request->validate([
            'rules' => 'required|array',
            'rules.*.name' => 'required|string',
            'rules.*.type' => 'required|string',
        ]);

        $userName = $request->user()?->name ?? 'System Admin';
        $count = 0;

        foreach ($validated['rules'] as $item) {
            SystemRule::create([
                'name' => $item['name'],
                'type' => $item['type'] ?? 'conditional_logic',
                'enabled' => $item['enabled'] ?? true,
                'status' => $item['status'] ?? 'active',
                'description' => $item['description'] ?? null,
                'rule_logic' => $item['rule_logic'] ?? [],
                'scope' => $item['scope'] ?? [],
                'actions' => $item['actions'] ?? [],
                'created_by' => $userName,
                'last_modified_by' => $userName,
            ]);
            $count++;
        }

        \App\Models\SystemLog::log('Import System Rules', "Imported {$count} system rule(s) via JSON.");

        return redirect()->back()->with('success', "Successfully imported {$count} system rule(s).");
    }

    /**
     * Store a newly created system role and its permissions.
     */
    public function storeRole(Request $request): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:roles,slug',
            'show_role_switcher' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:255',
        ]);

        $slug = !empty($validated['slug'])
            ? \Illuminate\Support\Str::slug($validated['slug'], '_')
            : \Illuminate\Support\Str::slug($validated['name'], '_');

        // Check uniqueness if slug generated
        $originalSlug = $slug;
        $counter = 1;
        while (\App\Models\Role::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}_{$counter}";
            $counter++;
        }

        $role = \App\Models\Role::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'show_role_switcher' => $request->has('show_role_switcher') ? $request->boolean('show_role_switcher') : true,
        ]);

        if (!empty($validated['permissions'])) {
            $uniquePerms = array_unique(array_filter(array_map('trim', $validated['permissions'])));
            foreach ($uniquePerms as $perm) {
                $role->permissions()->create(['permission' => $perm]);
            }
        }

        \App\Models\SystemLog::log('Create Role', "System role '{$role->name}' ({$role->slug}) created with " . count($validated['permissions'] ?? []) . " permission(s).");

        return redirect()->back()->with('success', "System role '{$role->name}' created successfully.");
    }

    /**
     * Update an existing system role and its permissions.
     */
    public function updateRole(Request $request, \App\Models\Role $role): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => "required|string|max:255|unique:roles,slug,{$role->id}",
            'show_role_switcher' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:255',
        ]);

        // Protect built-in admin role slug from being modified
        if ($role->slug === 'admin' && $validated['slug'] !== 'admin') {
            return redirect()->back()->with('error', "The built-in 'admin' slug cannot be changed.");
        }

        $roleData = [
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['slug'], '_'),
        ];
        if ($request->has('show_role_switcher')) {
            $roleData['show_role_switcher'] = $request->boolean('show_role_switcher');
        }

        $role->update($roleData);

        // Sync permissions
        $role->permissions()->delete();
        if (!empty($validated['permissions'])) {
            $uniquePerms = array_unique(array_filter(array_map('trim', $validated['permissions'])));
            foreach ($uniquePerms as $perm) {
                $role->permissions()->create(['permission' => $perm]);
            }
        }

        \App\Models\SystemLog::log('Update Role', "System role '{$role->name}' and its permissions updated.");

        return redirect()->back()->with('success', "System role '{$role->name}' and access permissions updated successfully.");
    }

    /**
     * Delete a system role.
     */
    public function destroyRole(\App\Models\Role $role): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        if (in_array($role->slug, ['admin', 'user'])) {
            return redirect()->back()->with('error', "The core system role '{$role->name}' cannot be deleted.");
        }

        if ($role->users()->count() > 0) {
            return redirect()->back()->with('error', "Cannot delete role '{$role->name}' because {$role->users()->count()} user(s) are currently assigned to it.");
        }

        $name = $role->name;
        $role->permissions()->delete();
        $role->delete();

        \App\Models\SystemLog::log('Delete Role', "System role '{$name}' was deleted.");

        return redirect()->back()->with('success', "System role '{$name}' deleted successfully.");
    }

    /**
     * Quick toggle the header role switcher menu display for a role.
     */
    public function toggleRoleSwitcher(Request $request, \App\Models\Role $role): RedirectResponse
    {
        Gate::authorize('manage-system-settings');

        $newState = !$role->show_role_switcher;
        $role->update(['show_role_switcher' => $newState]);

        $statusStr = $newState ? 'enabled' : 'disabled';
        \App\Models\SystemLog::log('Update Role Switcher', "Header role switcher menu {$statusStr} for system role '{$role->name}'.");

        return redirect()->back()->with('success', "Header role switcher menu {$statusStr} for role '{$role->name}'.");
    }
}
