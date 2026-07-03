<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $member = $user->member;

        // 1. Projects Query
        if ($user->hasRole('admin')) {
            $projectsQuery = Project::with('team.projectManager.user');
        } else {
            $teamIds = $member ? $member->teams->pluck('id')->toArray() : [];
            $projectsQuery = Project::whereIn('team_id', $teamIds)->with('team.projectManager.user');
        }
        
        $projects = $projectsQuery->get()->map(function (Project $project) {
            $totalTasks = $project->tasks()->count();
            $completedTasks = $project->tasks()->where('status', 'completed')->count();
            
            $project->total_tasks = $totalTasks;
            $project->completed_tasks = $completedTasks;
            $project->progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
            return $project;
        });

        // 2. Pending/Assigned Tasks
        if ($user->hasRole('admin')) {
            $tasksQuery = Task::where('status', '!=', 'completed')
                ->with(['project', 'developmentPhase', 'member.user']);
        } else {
            $tasksQuery = Task::where('member_id', $member?->id ?? 0)
                ->where('status', '!=', 'completed')
                ->with(['project', 'developmentPhase', 'member.user']);
        }
        $pendingTasks = $tasksQuery->get();

        // 3. Undelivered/Overdue Tasks (status != completed and current date > start_date + duration)
        $today = now()->startOfDay();
        
        if ($user->hasRole('admin')) {
            $undeliveredQuery = Task::where('status', '!=', 'completed')
                ->with(['project', 'developmentPhase', 'member.user']);
        } else {
            $undeliveredQuery = Task::where('member_id', $member?->id ?? 0)
                ->where('status', '!=', 'completed')
                ->with(['project', 'developmentPhase', 'member.user']);
        }

        $undeliveredTasks = $undeliveredQuery->get()->filter(function (Task $task) use ($today) {
            if (!$task->start_date) {
                return false;
            }
            $dueDate = $task->start_date->copy()->addDays($task->duration);
            return $today->greaterThan($dueDate);
        })->values();

        return Inertia::render('Dashboard', [
            'projects' => $projects,
            'pendingTasks' => $pendingTasks,
            'undeliveredTasks' => $undeliveredTasks,
            'isDeptHead' => $member && $member->memberRole && $member->memberRole->slug === 'department_head',
            'isProjectManager' => $member && $member->memberRole && $member->memberRole->slug === 'project_manager',
            'memberRole' => $member?->memberRole?->name ?? 'None',
            'systemRole' => $user->role?->name ?? 'User',
        ]);
    }
}
