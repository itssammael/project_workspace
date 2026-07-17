<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Member;
use App\Models\Section;
use App\Models\Role;
use App\Models\MemberRole;
use App\Models\Workflow;
use App\Models\WorkflowType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminManagementController extends Controller
{
    /**
     * Display the administration panel.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('admin');

        // Fetch users with roles, member, and section relations
        $users = User::with(['role', 'member.memberRoles', 'member.sections'])
            ->get()
            ->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'username' => $u->username,
                    'role_id' => $u->role_id,
                    'system_role' => $u->role?->name ?? 'User',
                    'system_role_slug' => $u->role?->slug ?? 'user',
                    'member_id' => $u->member?->id,
                    'member_role_ids' => $u->member ? $u->member->memberRoles->pluck('id')->toArray() : [],
                    'member_role' => $u->member && $u->member->memberRoles->isNotEmpty() ? $u->member->memberRoles->pluck('name')->implode(', ') : 'None',
                    'sections' => $u->member ? $u->member->sections->map(fn($t) => [
                        'id' => $t->id,
                        'name' => $t->name
                    ]) : [],
                ];
            });

        // Fetch sections with manager and assigned members
        $sections = Section::with(['projectManager.user', 'members.user', 'members.memberRoles'])
            ->get()
            ->map(function (Section $t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'member_id' => $t->member_id,
                    'project_manager' => $t->projectManager ? [
                        'id' => $t->projectManager->id,
                        'name' => $t->projectManager->user->name,
                        'email' => $t->projectManager->user->email,
                    ] : null,
                    'members' => $t->members->map(fn($m) => [
                        'id' => $m->id,
                        'name' => $m->user->name,
                        'email' => $m->user->email,
                        'role' => $m->memberRoles->isNotEmpty() ? $m->memberRoles->pluck('name')->implode(', ') : 'Developer',
                        'member_roles' => $m->memberRoles->map(fn($mr) => [
                            'id' => $mr->id,
                            'name' => $mr->name
                        ])
                    ]),
                ];
            });

        // Fetch support data for modals
        $roles = Role::all();
        $memberRoles = MemberRole::all();
        
        // Members list to populate managers and team assignments
        $membersList = Member::with(['user', 'memberRoles'])
            ->get()
            ->map(function (Member $m) {
                return [
                    'id' => $m->id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                    'role' => $m->memberRoles->isNotEmpty() ? $m->memberRoles->pluck('name')->implode(', ') : 'None',
                ];
            });

        // Fetch workflows
        $workflows = Workflow::with('workflowType')->orderBy('order', 'asc')->get()->map(function ($workflow) {
            return [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'order' => $workflow->order,
                'workflow_type_id' => $workflow->workflow_type_id,
                'workflow_type' => $workflow->workflowType?->name ?? 'General',
            ];
        });

        // Fetch system logs
        $systemLogs = \App\Models\SystemLog::orderBy('created_at', 'desc')->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user_name,
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        });

        $workflowTypes = WorkflowType::all()->map(fn($wt) => [
            'id' => $wt->id,
            'name' => $wt->name,
        ]);

        return Inertia::render('Admin/Management', compact('users', 'sections', 'roles', 'memberRoles', 'membersList', 'workflows', 'workflowTypes', 'systemLogs'));
    }

    /**
     * Store a new User and Member.
     */
    public function storeUser(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'member_role_ids' => 'nullable|array',
            'member_role_ids.*' => 'exists:member_roles,id',
        ]);

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
        ]);

        // Create the associated member
        $member = Member::create([
            'user_id' => $user->id,
        ]);
        
        $member->memberRoles()->sync($validated['member_role_ids'] ?? []);

        \App\Models\SystemLog::log('Create User', "User '{$user->name}' was created with role '{$user->role->name}'.");

        return redirect()->back()->with('success', 'User and member profile created successfully.');
    }

    /**
     * Update an existing User and Member.
     */
    public function updateUser(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'member_role_ids' => 'nullable|array',
            'member_role_ids.*' => 'exists:member_roles,id',
        ]);

        $userData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        // Update or create associated member
        $member = Member::firstOrCreate(['user_id' => $user->id]);
        $member->memberRoles()->sync($validated['member_role_ids'] ?? []);

        \App\Models\SystemLog::log('Update User', "User '{$user->name}' profile/roles were updated.");

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    /**
     * Delete a User and their Member profile.
     */
    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('admin');

        // Prevent admin from deleting themselves
        if ($user->id === $request->user()->id) {
            return redirect()->back()->withErrors(['error' => 'You cannot delete your own administrative user.']);
        }

        // Deleting the user will automatically cascade-delete the Member due to foreign key constraints
        $userName = $user->name;
        $user->delete();

        \App\Models\SystemLog::log('Delete User', "User '{$userName}' was deleted.");

        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    /**
     * Store a new Section and sync its members.
     */
    public function storeSection(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'member_id' => 'nullable|exists:members,id', // Project Manager
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:members,id',
        ]);

        $section = Section::create([
            'name' => $validated['name'],
            'member_id' => $validated['member_id'],
        ]);

        if (!empty($validated['member_ids'])) {
            $section->members()->sync($validated['member_ids']);
        }

        \App\Models\SystemLog::log('Create Section', "Section '{$section->name}' was created.");

        return redirect()->back()->with('success', 'Section created successfully.');
    }

    /**
     * Update an existing Section and its member syncs.
     */
    public function updateSection(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'member_id' => 'nullable|exists:members,id', // Project Manager
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:members,id',
        ]);

        $section->update([
            'name' => $validated['name'],
            'member_id' => $validated['member_id'],
        ]);

        $section->members()->sync($validated['member_ids'] ?? []);

        \App\Models\SystemLog::log('Update Section', "Section '{$section->name}' details or members list were updated.");

        return redirect()->back()->with('success', 'Section updated successfully.');
    }

    /**
     * Delete a Section.
     */
    public function destroySection(Section $section): RedirectResponse
    {
        Gate::authorize('admin');

        $sectionName = $section->name;
        $section->delete();

        \App\Models\SystemLog::log('Delete Section', "Section '{$sectionName}' was deleted.");

        return redirect()->back()->with('success', 'Section deleted successfully.');
    }

    /**
     * Store a new Development Phase.
     */
    public function storeWorkflow(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:workflows,name',
            'order' => 'required|integer|min:0',
            'workflow_type_id' => 'nullable|exists:workflow_types,id',
        ]);

        $workflow = Workflow::create($validated);

        \App\Models\SystemLog::log('Create Workflow', "Development Phase '{$workflow->name}' was created.");

        return redirect()->back()->with('success', 'Workflow created successfully.');
    }

    /**
     * Update an existing Workflow.
     */
    public function updateWorkflow(Request $request, Workflow $workflow): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:workflows,name,' . $workflow->id,
            'order' => 'required|integer|min:0',
            'workflow_type_id' => 'nullable|exists:workflow_types,id',
        ]);

        $workflow->update($validated);

        \App\Models\SystemLog::log('Update Workflow', "Development Phase '{$workflow->name}' was updated.");

        return redirect()->back()->with('success', 'Workflow updated successfully.');
    }

    /**
     * Delete a Workflow.
     */
    public function destroyWorkflow(Workflow $workflow): RedirectResponse
    {
        Gate::authorize('admin');

        $workflowName = $workflow->name;
        $workflow->delete();

        \App\Models\SystemLog::log('Delete Workflow', "Development Phase '{$workflowName}' was deleted.");

        return redirect()->back()->with('success', 'Workflow deleted successfully.');
    }

    /**
     * Store a new Workflow Type.
     */
    public function storeWorkflowType(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:workflow_types,name',
        ]);

        $workflowType = WorkflowType::create($validated);

        \App\Models\SystemLog::log('Create Workflow Type', "Workflow Type '{$workflowType->name}' was created.");

        return redirect()->back()->with('success', 'Workflow Type created successfully.');
    }

    /**
     * Update an existing Workflow Type.
     */
    public function updateWorkflowType(Request $request, WorkflowType $workflowType): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:workflow_types,name,' . $workflowType->id,
        ]);

        $workflowType->update($validated);

        \App\Models\SystemLog::log('Update Workflow Type', "Workflow Type '{$workflowType->name}' was updated.");

        return redirect()->back()->with('success', 'Workflow Type updated successfully.');
    }

    /**
     * Delete a Workflow Type.
     */
    public function destroyWorkflowType(WorkflowType $workflowType): RedirectResponse
    {
        Gate::authorize('admin');

        $workflowTypeName = $workflowType->name;
        $workflowType->delete();

        \App\Models\SystemLog::log('Delete Workflow Type', "Workflow Type '{$workflowTypeName}' was deleted.");

        return redirect()->back()->with('success', 'Workflow Type deleted successfully.');
    }

    /**
     * Bulk assign Development Phases to a Workflow Type.
     */
    public function bulkAssignWorkflows(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'workflow_ids' => 'required|array',
            'workflow_ids.*' => 'exists:workflows,id',
            'workflow_type_id' => 'nullable|string',
        ]);

        $workflowTypeId = $validated['workflow_type_id'];
        if ($workflowTypeId === 'uncategorized' || empty($workflowTypeId)) {
            $workflowTypeId = null;
        } else {
            $exists = DB::table('workflow_types')->where('id', $workflowTypeId)->exists();
            if (!$exists) {
                return redirect()->back()->withErrors(['workflow_type_id' => 'The selected workflow type is invalid.']);
            }
        }

        Workflow::whereIn('id', $validated['workflow_ids'])->update([
            'workflow_type_id' => $workflowTypeId
        ]);

        \App\Models\SystemLog::log('Bulk Assign Workflows', "Assigned " . count($validated['workflow_ids']) . " workflows to category ID: " . ($workflowTypeId ?? 'Uncategorized') . ".");

        return redirect()->back()->with('success', 'Workflows updated successfully.');
    }

    /**
     * Store a new Member Role.
     */
    public function storeMemberRole(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:member_roles,name',
        ]);

        $memberRole = MemberRole::create([
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
        ]);

        \App\Models\SystemLog::log('Create Member Role', "Functional Role '{$memberRole->name}' was created.");

        return redirect()->back()->with('success', 'Functional Role created successfully.');
    }

    /**
     * Update an existing Member Role.
     */
    public function updateMemberRole(Request $request, MemberRole $memberRole): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:member_roles,name,' . $memberRole->id,
        ]);

        $memberRole->update([
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
        ]);

        \App\Models\SystemLog::log('Update Member Role', "Functional Role '{$memberRole->name}' was updated.");

        return redirect()->back()->with('success', 'Functional Role updated successfully.');
    }

    /**
     * Delete a Member Role.
     */
    public function destroyMemberRole(MemberRole $memberRole): RedirectResponse
    {
        Gate::authorize('admin');

        $memberRoleName = $memberRole->name;
        // Detach role from members using it
        $memberRole->members()->detach();

        $memberRole->delete();

        \App\Models\SystemLog::log('Delete Member Role', "Functional Role '{$memberRoleName}' was deleted.");

        return redirect()->back()->with('success', 'Functional Role deleted successfully.');
    }

    /**
     * Bulk delete users.
     */
    public function bulkDestroyUsers(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
        ]);

        // Prevent admin from deleting themselves if they accidentally select themselves
        $currentUser = $request->user();
        $idsToDelete = array_filter($validated['ids'], function ($id) use ($currentUser) {
            return $id != $currentUser->id;
        });

        if (empty($idsToDelete)) {
            return redirect()->back()->withErrors(['ids' => 'No valid users selected for deletion.']);
        }

        $count = count($idsToDelete);
        DB::transaction(function () use ($idsToDelete) {
            // Delete associated member records
            Member::whereIn('user_id', $idsToDelete)->delete();
            // Delete users
            User::whereIn('id', $idsToDelete)->delete();
        });

        \App\Models\SystemLog::log('Bulk Delete Users', "Bulk deleted {$count} users.");

        return redirect()->back()->with('success', 'Selected users deleted successfully.');
    }

    /**
     * Attach a functional role to a member globally.
     */
    public function attachRoleToMember(Request $request, Member $member): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'member_role_id' => 'required|exists:member_roles,id',
        ]);

        // Only attach if not already globally assigned
        if (!$member->memberRoles()->where('member_roles.id', $validated['member_role_id'])->exists()) {
            $member->memberRoles()->attach($validated['member_role_id']);
        }

        $roleName = \App\Models\MemberRole::find($validated['member_role_id'])->name;
        \App\Models\SystemLog::log('Attach Role', "Attached functional role '{$roleName}' to member '{$member->user->name}'.");

        return redirect()->back()->with('success', 'Functional role added to member successfully.');
    }
}
