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
    public function index(Request $request): Response
    {
        $user = $request->user();
        $member = $user->member;

        if ($user->hasRole('admin')) {
            $projectsQuery = Project::with(['team.projectManager.user', 'tasks']);
        } else {
            $teamIds = $member ? $member->teams->pluck('id')->toArray() : [];
            $projectsQuery = Project::whereIn('team_id', $teamIds)->with(['team.projectManager.user', 'tasks']);
        }

        $projects = $projectsQuery->get()->map(function (Project $project) {
            $totalTasks = $project->tasks->count();
            $completedTasks = $project->tasks->where('status', 'completed')->count();
            
            $project->total_tasks = $totalTasks;
            $project->completed_tasks = $completedTasks;
            $project->progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
            return $project;
        });

        $canCreateProjects = Gate::allows('create-projects');
        $canDeleteProjects = $user->hasRole('admin');

        return Inertia::render('Project/Index', [
            'projects' => $projects,
            'canCreateProjects' => $canCreateProjects,
            'canDeleteProjects' => $canDeleteProjects,
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view-project', $project);

        // Load team and tasks with assignees and phases
        $project->load(['team.projectManager.user', 'team.members.user']);

        // Project-level progress
        $totalTasks = $project->tasks()->count();
        $completedTasks = $project->tasks()->where('status', 'completed')->count();
        $project->total_tasks = $totalTasks;
        $project->completed_tasks = $completedTasks;
        $project->progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        // Aggregate development phases and tasks ordered by phase
        $phases = $project->developmentPhases()->orderBy('order', 'asc')->get()->map(function ($phase) use ($project) {
            $tasks = $project->tasks()
                ->where('development_phase_id', $phase->id)
                ->with('member.user')
                ->get();
            $phase->tasks = $tasks;

            $phaseTotal = $tasks->count();
            $phaseCompleted = $tasks->where('status', 'completed')->count();
            $phase->total_tasks = $phaseTotal;
            $phase->completed_tasks = $phaseCompleted;
            $phase->progress = $phaseTotal > 0 ? round(($phaseCompleted / $phaseTotal) * 100) : 0;

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

        $teams = $request->user()->hasRole('admin') ? Team::all() : [];

        return Inertia::render('Project/Show', [
            'project' => $project,
            'phases' => $phases,
            'teamMembers' => $teamMembers,
            'canManageTasks' => Gate::allows('manage-tasks', $project),
            'canDeleteProject' => $request->user()->hasRole('admin'),
            'teams' => $teams,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create-projects');

        $teams = Team::with('projectManager.user')->get();
        $phases = DevelopmentPhase::with('developmentType')->orderBy('order', 'asc')->get()->map(function ($phase) {
            return [
                'id' => $phase->id,
                'name' => $phase->name,
                'order' => $phase->order,
                'project_type' => $phase->developmentType?->name ?? 'General',
            ];
        });
        
        return Inertia::render('Project/Create', [
            'teams' => $teams,
            'phases' => $phases,
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
            'phase_ids' => 'required|array|min:1',
            'phase_ids.*' => 'exists:development_phases,id',
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'team_id' => $validated['team_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        $project->developmentPhases()->sync($validated['phase_ids']);

        return redirect()->route('dashboard')->with('success', 'Project created successfully.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        $project->update([
            'team_id' => $validated['team_id'],
        ]);

        return redirect()->back()->with('success', 'Project team updated successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('admin');

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted successfully.');
    }
}
