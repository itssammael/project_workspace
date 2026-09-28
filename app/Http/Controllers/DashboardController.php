<?php

namespace App\Http\Controllers;

use App\Models\TaskBoard;
use App\Models\Task;
use App\Services\DashboardRoleService;
use App\Services\DashboardDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the role-based operational dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing(['role', 'member.sections', 'member.memberRoles']);
        $member = $user->member;

        // 1. Resolve role authorization and active view
        $activeView = DashboardRoleService::getActiveView($request, $user);
        $availableViews = DashboardRoleService::getAvailableViewsWithMeta($user);
        $userInfo = DashboardRoleService::getUserOrganizationalInfo($user);

        // 2. Fetch specific dataset tailored for the active view
        $viewData = match ($activeView) {
            DashboardRoleService::VIEW_ADMIN => DashboardDataService::getAdminData($user),
            DashboardRoleService::VIEW_DEPARTMENT_HEAD => DashboardDataService::getDepartmentHeadData($user),
            DashboardRoleService::VIEW_SUPERVISOR => DashboardDataService::getSupervisorData($user),
            default => DashboardDataService::getStaffData($user),
        };

        // 3. Backward-compatible task boards query
        $canViewAll = \App\Services\SystemRuleEvaluator::checkOperation($user, 'view_all_task_boards_dashboard', ['admin'], [])
            || \App\Services\SystemRuleEvaluator::checkOperation($user, 'view_all_projects_dashboard', ['admin'], []);

        if ($canViewAll) {
            $taskBoardsQuery = TaskBoard::with('section.projectManager.user');
        } else {
            $taskBoardsQuery = \App\Services\SystemRuleEvaluator::scopeTaskBoardQuery(
                TaskBoard::with('section.projectManager.user'),
                $user
            );
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

        // 4. Backward-compatible pending & overdue tasks (used by legacy components or fallbacks)
        $subTasksQuery = \App\Models\SubTask::where('status', '!=', 'completed')
            ->with(['task.taskBoard', 'task.workflow', 'member.user']);
        
        if (!$canViewAll) {
            $subTasksQuery->where('member_id', $member?->id ?? 0);
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
                'priority' => $subTask->priority ?: 'medium',
                'is_blocked' => (bool)$subTask->is_blocked,
                'blocked_reason' => $subTask->blocked_reason,
                'parent_task_name' => $subTask->task?->name,
                'task_board' => $subTask->task?->taskBoard,
                'project' => $subTask->task?->taskBoard,
                'workflow' => $subTask->task?->workflow,
            ];
        })->values();

        $today = now()->startOfDay();
        $undeliveredTasks = $pendingTasks->filter(function ($subTask) use ($today) {
            if (!$subTask['start_date']) {
                return false;
            }
            $dueDate = \Carbon\Carbon::parse($subTask['start_date'])->addDays($subTask['duration']);
            return $today->greaterThan($dueDate);
        })->values();

        return Inertia::render('Dashboard', [
            'activeView' => $activeView,
            'availableViews' => $availableViews,
            'userInfo' => $userInfo,
            'viewData' => $viewData,

            // Legacy / backward-compatible props
            'taskBoards' => $taskBoards,
            'projects' => $taskBoards,
            'pendingTasks' => $pendingTasks,
            'undeliveredTasks' => $undeliveredTasks,
            'isDeptHead' => in_array('department_head', DashboardRoleService::getAuthorizedViews($user)),
            'isProjectManager' => $member && $member->memberRoles()->where('slug', 'project_manager')->exists(),
            'memberRole' => $userInfo['functional_roles_string'],
            'systemRole' => $userInfo['system_role'],
        ]);
    }

    /**
     * Switch the active dashboard view dynamically.
     */
    public function switchView(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'view' => 'required|string|in:staff,supervisor,department_head,admin',
        ]);

        $authorizedViews = DashboardRoleService::getAuthorizedViews($user);
        if (in_array($validated['view'], $authorizedViews, true)) {
            $request->session()->put('dashboard_active_view', $validated['view']);
        }

        return redirect()->route('dashboard');
    }

    /**
     * Generate / export report stream for Department Head view.
     */
    public function exportReport(Request $request)
    {
        $user = $request->user();
        $authorized = DashboardRoleService::getAuthorizedViews($user);
        if (!in_array('department_head', $authorized) && !in_array('admin', $authorized)) {
            abort(403, 'Unauthorized report access.');
        }

        $type = $request->query('type', 'department_summary');
        $format = $request->query('format', 'csv');

        $data = DashboardDataService::getDepartmentHeadData($user);

        if ($format === 'csv') {
            $fileName = "report_{$type}_" . date('Y-m-d_His') . ".csv";
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ];

            $callback = function () use ($type, $data) {
                $file = fopen('php://output', 'w');

                if ($type === 'section_performance') {
                    fputcsv($file, ['Section', 'Department', 'Manager', 'Total Tasks', 'Completed Tasks', 'Active Tasks', 'Overdue Tasks', 'Completion Rate (%)']);
                    foreach ($data['section_performance'] as $sec) {
                        fputcsv($file, [
                            $sec['name'],
                            $sec['department_name'],
                            $sec['manager_name'],
                            $sec['total_tasks'],
                            $sec['completed_tasks'],
                            $sec['active_tasks'],
                            $sec['overdue_tasks'],
                            $sec['completion_rate'] . '%',
                        ]);
                    }
                } elseif ($type === 'milestone_status' || $type === 'project_progress') {
                    fputcsv($file, ['Task Board', 'Section', 'Status', 'Start Date', 'End Date', 'Progress (%)', 'Completed Tasks', 'Total Tasks']);
                    foreach ($data['department_roadmap'] as $b) {
                        fputcsv($file, [
                            $b['name'],
                            $b['section_name'],
                            $b['status'],
                            $b['start_date'],
                            $b['end_date'],
                            $b['progress'] . '%',
                            $b['completed_tasks'],
                            $b['total_tasks'],
                        ]);
                    }
                } else {
                    fputcsv($file, ['Metric', 'Value']);
                    fputcsv($file, ['Active Task Boards', $data['executive_summary']['active_task_boards']]);
                    fputcsv($file, ['Completed Task Boards', $data['executive_summary']['completed_task_boards']]);
                    fputcsv($file, ['Department Completion Rate', $data['executive_summary']['department_completion_rate'] . '%']);
                    fputcsv($file, ['Timeline - On Track', $data['executive_summary']['timeline_health']['on_track']]);
                    fputcsv($file, ['Timeline - At Risk', $data['executive_summary']['timeline_health']['at_risk']]);
                    fputcsv($file, ['Timeline - Delayed', $data['executive_summary']['timeline_health']['delayed']]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        return redirect()->back();
    }
}
