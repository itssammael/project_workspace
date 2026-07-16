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
            $projectsQuery = Project::with('section.projectManager.user');
        } else {
            $sectionIds = $member ? $member->sections->pluck('id')->toArray() : [];
            $projectsQuery = Project::whereIn('section_id', $sectionIds)->with('section.projectManager.user');
        }
        
        $projects = $projectsQuery->get()->map(function (Project $project) {
            $taskIds = $project->tasks()->pluck('id');
            $totalTasks = \App\Models\SubTask::whereIn('task_id', $taskIds)->count();
            $completedTasks = \App\Models\SubTask::whereIn('task_id', $taskIds)->where('status', 'completed')->count();
            
            $project->total_tasks = $totalTasks;
            $project->completed_tasks = $completedTasks;
            $project->progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
            return $project;
        });

        // 2. Pending/Assigned Tasks
        if ($user->hasRole('admin')) {
            $subTasksQuery = \App\Models\SubTask::where('status', '!=', 'completed')
                ->with(['task.project', 'task.workflow', 'member.user']);
        } else {
            $subTasksQuery = \App\Models\SubTask::where('member_id', $member?->id ?? 0)
                ->where('status', '!=', 'completed')
                ->with(['task.project', 'task.workflow', 'member.user']);
        }
        $pendingTasks = $subTasksQuery->get()->map(function ($subTask) {
            return [
                'id' => $subTask->id,
                'name' => $subTask->name,
                'details' => $subTask->details,
                'deliverables' => $subTask->deliverables,
                'duration' => $subTask->duration,
                'member_id' => $subTask->member_id,
                'start_date' => $subTask->start_date ? \Carbon\Carbon::parse($subTask->start_date)->format('Y-m-d') : null,
                'status' => $subTask->status,
                'parent_task_name' => $subTask->task?->name,
                'project' => $subTask->task?->project,
                'workflow' => $subTask->task?->workflow,
            ];
        })->values();

        // 3. Undelivered/Overdue Tasks (status != completed and current date > start_date + duration)
        $today = now()->startOfDay();
        
        if ($user->hasRole('admin')) {
            $undeliveredSubQuery = \App\Models\SubTask::where('status', '!=', 'completed')
                ->with(['task.project', 'task.workflow', 'member.user']);
        } else {
            $undeliveredSubQuery = \App\Models\SubTask::where('member_id', $member?->id ?? 0)
                ->where('status', '!=', 'completed')
                ->with(['task.project', 'task.workflow', 'member.user']);
        }

        $undeliveredTasks = $undeliveredSubQuery->get()->filter(function ($subTask) use ($today) {
            if (!$subTask->start_date) {
                return false;
            }
            $dueDate = $subTask->start_date->copy()->addDays($subTask->duration);
            return $today->greaterThan($dueDate);
        })->map(function ($subTask) {
            return [
                'id' => $subTask->id,
                'name' => $subTask->name,
                'details' => $subTask->details,
                'deliverables' => $subTask->deliverables,
                'duration' => $subTask->duration,
                'member_id' => $subTask->member_id,
                'start_date' => $subTask->start_date ? \Carbon\Carbon::parse($subTask->start_date)->format('Y-m-d') : null,
                'status' => $subTask->status,
                'parent_task_name' => $subTask->task?->name,
                'project' => $subTask->task?->project,
                'workflow' => $subTask->task?->workflow,
            ];
        })->values();

        return Inertia::render('Dashboard', [
            'projects' => $projects,
            'pendingTasks' => $pendingTasks,
            'undeliveredTasks' => $undeliveredTasks,
            'isDeptHead' => $member && $member->memberRoles()->where('slug', 'department_head')->exists(),
            'isProjectManager' => $member && $member->memberRoles()->where('slug', 'project_manager')->exists(),
            'memberRole' => $member ? $member->memberRoles->pluck('name')->implode(', ') : 'None',
            'systemRole' => $user->role?->name ?? 'User',
        ]);
    }
}
