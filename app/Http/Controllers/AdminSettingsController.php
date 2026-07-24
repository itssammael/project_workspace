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
        Gate::authorize('manage-users');

        $settings = [
            'system_name' => Setting::get('system_name', 'Project Tracker'),
            'theme' => Setting::get('theme', 'corporate_teal'),
            'logo' => Setting::get('logo', null),
        ];

        $systemRules = SystemRule::orderBy('created_at', 'desc')->get();

        $roles = \App\Models\Role::all();
        $memberRoles = \App\Models\MemberRole::all();

        return Inertia::render('Admin/Settings', compact('settings', 'systemRules', 'roles', 'memberRoles'));
    }

    /**
     * Update the general system settings.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');

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
        Gate::authorize('manage-users');

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
        Gate::authorize('manage-users');

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
        Gate::authorize('manage-users');

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
        Gate::authorize('manage-users');

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
        Gate::authorize('manage-users');

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
        Gate::authorize('manage-users');

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
}
