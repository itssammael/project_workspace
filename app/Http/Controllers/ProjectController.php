<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Team;
use App\Models\DevelopmentPhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ProjectController extends Controller
{
    public function show(Project $project): Response
    {
        // Load team and tasks with assignees and phases
        $project->load(['team.projectManager.user', 'team.members.user']);

        // Aggregate development phases and tasks ordered by phase
        $phases = DevelopmentPhase::orderBy('order', 'asc')->get()->map(function ($phase) use ($project) {
            $tasks = $project->tasks()
                ->where('development_phase_id', $phase->id)
                ->with('member.user')
                ->get();
            $phase->tasks = $tasks;
            return $phase;
        });

        // Let's also pass the list of all members in the team so we can assign tasks
        $teamMembers = $project->team ? $project->team->members->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'role' => $m->memberRole?->name ?? 'Member',
            ];
        }) : [];

        return Inertia::render('Project/Show', [
            'project' => $project,
            'phases' => $phases,
            'teamMembers' => $teamMembers,
            'canManageTasks' => Gate::allows('manage-tasks', $project),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create-projects');

        $teams = Team::with('projectManager.user')->get();
        return Inertia::render('Project/Create', [
            'teams' => $teams,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create-projects');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string|in:planning,active,completed,on_hold',
            'team_id' => 'required|exists:teams,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        Project::create($validated);

        return redirect()->route('dashboard')->with('success', 'Project created successfully.');
    }
}
