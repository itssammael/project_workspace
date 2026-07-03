<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Member;
use App\Models\Team;
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

        // Fetch users with roles, member, and team relations
        $users = User::with(['role', 'member.memberRole', 'member.teams'])
            ->get()
            ->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role_id' => $u->role_id,
                    'system_role' => $u->role?->name ?? 'User',
                    'system_role_slug' => $u->role?->slug ?? 'user',
                    'member_id' => $u->member?->id,
                    'member_role_id' => $u->member?->member_role_id,
                    'member_role' => $u->member?->memberRole?->name ?? 'None',
                    'teams' => $u->member ? $u->member->teams->map(fn($t) => [
                        'id' => $t->id,
                        'name' => $t->name
                    ]) : [],
                ];
            });

        // Fetch teams with manager and assigned members
        $teams = Team::with(['projectManager.user', 'members.user', 'members.memberRole'])
            ->get()
            ->map(function (Team $t) {
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
                        'role' => $m->memberRole?->name ?? 'Developer',
                    ]),
                ];
            });

        // Fetch support data for modals
        $roles = Role::all();
        $memberRoles = MemberRole::all();
        
        // Members list to populate managers and team assignments
        $membersList = Member::with('user', 'memberRole')
            ->get()
            ->map(function (Member $m) {
                return [
                    'id' => $m->id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                    'role' => $m->memberRole?->name ?? 'None',
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

        return Inertia::render('Admin/Management', compact('users', 'teams', 'roles', 'memberRoles', 'membersList', 'phases', 'developmentTypes'));
    }

    /**
     * Store a new User and Member.
     */
    public function storeUser(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'member_role_id' => 'nullable|exists:member_roles,id',
        ]);

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
        ]);

        // Create the associated member
        Member::create([
            'user_id' => $user->id,
            'member_role_id' => $validated['member_role_id'],
        ]);

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
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'member_role_id' => 'nullable|exists:member_roles,id',
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        // Update or create associated member
        $member = Member::firstOrCreate(['user_id' => $user->id]);
        $member->update([
            'member_role_id' => $validated['member_role_id'],
        ]);

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
     * Store a new Team and sync its members.
     */
    public function storeTeam(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'member_id' => 'nullable|exists:members,id', // Project Manager
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:members,id',
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'member_id' => $validated['member_id'],
        ]);

        if (!empty($validated['member_ids'])) {
            $team->members()->sync($validated['member_ids']);
        }

        return redirect()->back()->with('success', 'Team created successfully.');
    }

    /**
     * Update an existing Team and its member syncs.
     */
    public function updateTeam(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'member_id' => 'nullable|exists:members,id', // Project Manager
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:members,id',
        ]);

        $team->update([
            'name' => $validated['name'],
            'member_id' => $validated['member_id'],
        ]);

        $team->members()->sync($validated['member_ids'] ?? []);

        return redirect()->back()->with('success', 'Team updated successfully.');
    }

    /**
     * Delete a Team.
     */
    public function destroyTeam(Team $team): RedirectResponse
    {
        Gate::authorize('admin');

        $team->delete();

        return redirect()->back()->with('success', 'Team deleted successfully.');
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
}
