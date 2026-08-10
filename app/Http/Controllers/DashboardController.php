<?php

namespace App\Http\Controllers;

use App\Models\TaskBoard;
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

        $canViewAll = \App\Services\SystemRuleEvaluator::checkOperation($user, 'view_all_task_boards_dashboard', ['admin'], [])
            || \App\Services\SystemRuleEvaluator::checkOperation($user, 'view_all_projects_dashboard', ['admin'], []);

        // 1. Task Boards Query
        if ($canViewAll) {
            $taskBoardsQuery = TaskBoard::with('section.projectManager.user');
        } else {
            $sectionIds = $member ? $member->sections->pluck('id')->toArray() : [];
            $taskBoardsQuery = TaskBoard::whereIn('section_id', $sectionIds)->with('section.projectManager.user');
        }
        
        $taskBoards = $taskBoardsQuery->get()->map(function (TaskBoard $taskBoard) {
            $taskIds = $taskBoard->tasks()->pluck('id');
            $totalTasks = \App\Models\SubTask::whereIn('task_id', $taskIds)->count();
            $completedTasks = \App\Models\SubTask::whereIn('task_id', $taskIds)->where('status', 'completed')->count();
            
            $taskBoard->total_tasks = $totalTasks;
            $taskBoard->completed_tasks = $completedTasks;
            $taskBoard->progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
            return $taskBoard;
        });

        // 2. Pending/Assigned Tasks
        if ($canViewAll) {
            $subTasksQuery = \App\Models\SubTask::where('status', '!=', 'completed')
                ->with(['task.taskBoard', 'task.workflow', 'member.user']);
        } else {
            $subTasksQuery = \App\Models\SubTask::where('member_id', $member?->id ?? 0)
                ->where('status', '!=', 'completed')
                ->with(['task.taskBoard', 'task.workflow', 'member.user']);
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
                'task_board' => $subTask->task?->taskBoard,
                'project' => $subTask->task?->taskBoard,
                'workflow' => $subTask->task?->workflow,
            ];
        })->values();

        // 3. Undelivered/Overdue Tasks (status != completed and current date > start_date + duration)
        $today = now()->startOfDay();
        
        if ($canViewAll) {
            $undeliveredSubQuery = \App\Models\SubTask::where('status', '!=', 'completed')
                ->with(['task.taskBoard', 'task.workflow', 'member.user']);
        } else {
            $undeliveredSubQuery = \App\Models\SubTask::where('member_id', $member?->id ?? 0)
                ->where('status', '!=', 'completed')
                ->with(['task.taskBoard', 'task.workflow', 'member.user']);
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
                'task_board' => $subTask->task?->taskBoard,
                'project' => $subTask->task?->taskBoard,
                'workflow' => $subTask->task?->workflow,
            ];
        })->values();

        return Inertia::render('Dashboard', [
            'taskBoards' => $taskBoards,
            'projects' => $taskBoards,
            'pendingTasks' => $pendingTasks,
            'undeliveredTasks' => $undeliveredTasks,
            'isDeptHead' => $member && $member->memberRoles()->where('slug', 'department_head')->exists(),
            'isProjectManager' => $member && $member->memberRoles()->where('slug', 'project_manager')->exists(),
            'memberRole' => $member ? $member->memberRoles->pluck('name')->implode(', ') : 'None',
            'systemRole' => $user->role?->name ?? 'User',
        ]);
    }
}
