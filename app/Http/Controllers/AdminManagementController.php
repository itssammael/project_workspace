<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Member;
use App\Models\Section;
use App\Models\Role;
use App\Models\MemberRole;
use App\Models\DevelopmentPhase;
use App\Models\DevelopmentType;
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

        // Fetch phases
        $phases = DevelopmentPhase::with('developmentType')->orderBy('order', 'asc')->get()->map(function ($phase) {
            return [
                'id' => $phase->id,
                'name' => $phase->name,
                'order' => $phase->order,
                'development_type_id' => $phase->development_type_id,
                'project_type' => $phase->developmentType?->name ?? 'General',
            ];
        });

        // Fetch development types
        $developmentTypes = DevelopmentType::orderBy('name', 'asc')->get()->map(function ($dt) {
            return [
                'id' => $dt->id,
                'name' => $dt->name,
            ];
        });

        return Inertia::render('Admin/Management', compact('users', 'sections', 'roles', 'memberRoles', 'membersList', 'phases', 'developmentTypes'));
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
        $user->delete();

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

        return redirect()->back()->with('success', 'Section updated successfully.');
    }

    /**
     * Delete a Section.
     */
    public function destroySection(Section $section): RedirectResponse
    {
        Gate::authorize('admin');

        $section->delete();

        return redirect()->back()->with('success', 'Section deleted successfully.');
    }

    /**
     * Store a new Development Phase.
     */
    public function storePhase(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:development_phases,name',
            'order' => 'required|integer|min:0',
            'development_type_id' => 'nullable|exists:development_types,id',
        ]);

        DevelopmentPhase::create($validated);

        return redirect()->back()->with('success', 'Development Phase created successfully.');
    }

    /**
     * Update an existing Development Phase.
     */
    public function updatePhase(Request $request, DevelopmentPhase $phase): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:development_phases,name,' . $phase->id,
            'order' => 'required|integer|min:0',
            'development_type_id' => 'nullable|exists:development_types,id',
        ]);

        $phase->update($validated);

        return redirect()->back()->with('success', 'Development Phase updated successfully.');
    }

    /**
     * Delete a Development Phase.
     */
    public function destroyPhase(DevelopmentPhase $phase): RedirectResponse
    {
        Gate::authorize('admin');

        $phase->delete();

        return redirect()->back()->with('success', 'Development Phase deleted successfully.');
    }

    /**
     * Store a new Development Type.
     */
    public function storeDevelopmentType(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:development_types,name',
        ]);

        DevelopmentType::create($validated);

        return redirect()->back()->with('success', 'Development Type created successfully.');
    }

    /**
     * Update an existing Development Type.
     */
    public function updateDevelopmentType(Request $request, DevelopmentType $developmentType): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:development_types,name,' . $developmentType->id,
        ]);

        $developmentType->update($validated);

        return redirect()->back()->with('success', 'Development Type updated successfully.');
    }

    /**
     * Delete a Development Type.
     */
    public function destroyDevelopmentType(DevelopmentType $developmentType): RedirectResponse
    {
        Gate::authorize('admin');

        $developmentType->delete();

        return redirect()->back()->with('success', 'Development Type deleted successfully.');
    }

    /**
     * Bulk assign Development Phases to a Development Type.
     */
    public function bulkAssignPhases(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'phase_ids' => 'required|array',
            'phase_ids.*' => 'exists:development_phases,id',
            'development_type_id' => 'nullable|string',
        ]);

        $devTypeId = $validated['development_type_id'];
        if ($devTypeId === 'uncategorized' || empty($devTypeId)) {
            $devTypeId = null;
        } else {
            $exists = DB::table('development_types')->where('id', $devTypeId)->exists();
            if (!$exists) {
                return redirect()->back()->withErrors(['development_type_id' => 'The selected development type is invalid.']);
            }
        }

        DevelopmentPhase::whereIn('id', $validated['phase_ids'])->update([
            'development_type_id' => $devTypeId
        ]);

        return redirect()->back()->with('success', 'Development Phases updated successfully.');
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

        MemberRole::create([
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
        ]);

        return redirect()->back()->with('success', 'Functional Role created successfully.');
    }

    /**
     * Delete a Member Role.
     */
    public function destroyMemberRole(MemberRole $memberRole): RedirectResponse
    {
        Gate::authorize('admin');

        // Detach role from members using it
        $memberRole->members()->detach();

        $memberRole->delete();

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

        DB::transaction(function () use ($idsToDelete) {
            // Delete associated member records
            Member::whereIn('user_id', $idsToDelete)->delete();
            // Delete users
            User::whereIn('id', $idsToDelete)->delete();
        });

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

        return redirect()->back()->with('success', 'Functional role added to member successfully.');
    }
}
