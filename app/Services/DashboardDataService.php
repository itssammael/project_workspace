<?php

namespace App\Services;

use App\Models\User;
use App\Models\Member;
use App\Models\Section;
use App\Models\Department;
use App\Models\TaskBoard;
use App\Models\Task;
use App\Models\SubTask;
use App\Models\SubTaskComment;
use App\Models\SubTaskAttachment;
use App\Models\SystemLog;
use App\Models\SystemRule;
use App\Models\WorkflowType;
use App\Models\MemberRole;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardDataService
{
    /**
     * Build Staff Dashboard dataset.
     */
    public static function getStaffData(User $user): array
    {
        $member = $user->member;
        $memberId = $member?->id ?? 0;
        $today = Carbon::now()->startOfDay();

        // 1. My Action Items (All subtasks assigned to the user)
        $subTasks = SubTask::where('member_id', $memberId)
            ->with(['task.taskBoard.section', 'task.workflow'])
            ->get();

        $actionItems = $subTasks->map(function (SubTask $subTask) use ($today) {
            $startDate = $subTask->start_date ? Carbon::parse($subTask->start_date)->startOfDay() : null;
            $dueDate = $startDate ? $startDate->copy()->addDays($subTask->duration) : null;
            
            $isOverdue = $dueDate && $today->greaterThan($dueDate) && $subTask->status !== 'completed';
            $isDueToday = $dueDate && $today->equalTo($dueDate) && $subTask->status !== 'completed';
            $isUpcoming = $dueDate && $today->lessThan($dueDate) && $subTask->status !== 'completed';

            // Effective priority
            $priority = $subTask->priority ?: ($isOverdue ? 'urgent' : 'medium');

            return [
                'id' => $subTask->id,
                'name' => $subTask->name,
                'details' => $subTask->details,
                'deliverables' => $subTask->deliverables,
                'duration' => $subTask->duration,
                'status' => $subTask->status,
                'priority' => strtolower($priority),
                'is_blocked' => (bool)$subTask->is_blocked,
                'blocked_reason' => $subTask->blocked_reason,
                'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
                'due_date' => $dueDate ? $dueDate->format('Y-m-d') : null,
                'is_overdue' => $isOverdue,
                'is_due_today' => $isDueToday,
                'is_upcoming' => $isUpcoming,
                'parent_task_name' => $subTask->task?->name,
                'task_board_id' => $subTask->task?->taskBoard?->id,
                'task_board_name' => $subTask->task?->taskBoard?->name,
                'task_board_status' => $subTask->task?->taskBoard?->status,
                'section_name' => $subTask->task?->taskBoard?->section?->name,
                'workflow_name' => $subTask->task?->workflow?->name,
                'updated_at' => $subTask->updated_at?->toIso8601String(),
            ];
        })->sortBy(function ($item) {
            // Sort: urgent first, then overdue, then due date
            $priorityWeight = match($item['priority']) {
                'urgent' => 1,
                'high' => 2,
                'medium' => 3,
                'low' => 4,
                default => 3,
            };
            return sprintf('%d_%d_%s', $item['is_overdue'] ? 0 : 1, $priorityWeight, $item['due_date'] ?? '9999-99-99');
        })->values()->toArray();

        // 2. Quick Metrics
        $completedToday = SubTask::where('member_id', $memberId)
            ->where('status', 'completed')
            ->whereDate('updated_at', Carbon::today())
            ->count();

        $completedThisWeek = SubTask::where('member_id', $memberId)
            ->where('status', 'completed')
            ->whereBetween('updated_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();

        $pendingTasks = SubTask::where('member_id', $memberId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $overdueCount = collect($actionItems)->where('is_overdue', true)->count();

        $quickMetrics = [
            'completed_today' => $completedToday,
            'completed_this_week' => $completedThisWeek,
            'pending_tasks' => $pendingTasks,
            'overdue_tasks' => $overdueCount,
        ];

        // 3. Recent Activity & Mentions
        $mySubTaskIds = $subTasks->pluck('id')->toArray();
        $recentComments = SubTaskComment::whereIn('sub_task_id', $mySubTaskIds)
            ->orWhere('member_id', $memberId)
            ->with(['subTask.task.taskBoard', 'member.user'])
            ->latest()
            ->take(8)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'type' => 'comment',
                    'actor_name' => $c->member?->user?->name ?? 'Colleague',
                    'actor_avatar' => $c->member?->user?->profile_photo_url,
                    'text' => $c->comment,
                    'target_title' => $c->subTask?->name ?? 'Task',
                    'task_board_name' => $c->subTask?->task?->taskBoard?->name,
                    'task_board_id' => $c->subTask?->task?->taskBoard?->id,
                    'created_at' => $c->created_at?->diffForHumans() ?? 'recently',
                    'timestamp' => $c->created_at?->toIso8601String(),
                ];
            });

        $recentAttachments = SubTaskAttachment::whereIn('sub_task_id', $mySubTaskIds)
            ->with('subTask.task.taskBoard')
            ->latest()
            ->take(4)
            ->get()
            ->map(function ($att) {
                return [
                    'id' => $att->id,
                    'type' => 'file',
                    'actor_name' => 'Team',
                    'actor_avatar' => null,
                    'text' => 'Uploaded deliverable: ' . basename($att->attachment ?? 'file'),
                    'target_title' => $att->subTask?->name ?? 'Task',
                    'task_board_name' => $att->subTask?->task?->taskBoard?->name,
                    'task_board_id' => $att->subTask?->task?->taskBoard?->id,
                    'created_at' => $att->created_at?->diffForHumans() ?? 'recently',
                    'timestamp' => $att->created_at?->toIso8601String(),
                ];
            });

        $recentActivity = $recentComments->concat($recentAttachments)
            ->sortByDesc('timestamp')
            ->values()
            ->take(10)
            ->toArray();

        // 4. Task Board Shortcuts
        $taskBoards = TaskBoard::where(function ($q) use ($memberId) {
                if ($memberId) {
                    $q->whereHas('members', fn($sq) => $sq->where('members.id', $memberId))
                      ->orWhereHas('section.members', fn($sq) => $sq->where('members.id', $memberId));
                }
            })
            ->distinct()
            ->with('section')
            ->get()
            ->map(function (TaskBoard $board) {
                $taskIds = $board->tasks()->pluck('id');
                $total = SubTask::whereIn('task_id', $taskIds)->count();
                $completed = SubTask::whereIn('task_id', $taskIds)->where('status', 'completed')->count();
                $progress = $total > 0 ? round(($completed / $total) * 100) : 0;

                return [
                    'id' => $board->id,
                    'name' => $board->name,
                    'description' => $board->description,
                    'status' => $board->status,
                    'section_name' => $board->section?->name,
                    'progress' => $progress,
                    'total_tasks' => $total,
                    'completed_tasks' => $completed,
                    'start_date' => $board->start_date ? Carbon::parse($board->start_date)->format('M d, Y') : null,
                    'end_date' => $board->end_date ? Carbon::parse($board->end_date)->format('M d, Y') : null,
                ];
            })->values()->toArray();

        return [
            'action_items' => $actionItems,
            'quick_metrics' => $quickMetrics,
            'recent_activity' => $recentActivity,
            'task_board_shortcuts' => $taskBoards,
        ];
    }

    /**
     * Build Supervisor Dashboard dataset.
     */
    public static function getSupervisorData(User $user): array
    {
        $member = $user->member;
        $memberId = $member?->id ?? 0;
        $today = Carbon::now()->startOfDay();

        // 1. Determine supervised section IDs
        if ($user->hasRole('admin')) {
            $supervisedSectionIds = Section::pluck('id')->toArray();
        } else {
            $supervisedSectionIds = [];
            
            // Section where user is PM / manager
            if ($memberId) {
                $pmSections = Section::where('member_id', $memberId)->pluck('id')->toArray();
                $supervisedSectionIds = array_merge($supervisedSectionIds, $pmSections);
                
                // Headed departments
                $headedDepts = Department::where('department_head_id', $memberId)->pluck('id')->toArray();
                if (!empty($headedDepts)) {
                    $deptSections = Section::whereIn('department_id', $headedDepts)->pluck('id')->toArray();
                    $supervisedSectionIds = array_merge($supervisedSectionIds, $deptSections);
                }

                // Sections where member belongs
                $memberSections = $member->sections()->pluck('sections.id')->toArray();
                $supervisedSectionIds = array_merge($supervisedSectionIds, $memberSections);
            }

            $supervisedSectionIds = array_values(array_unique($supervisedSectionIds));
            
            // Fallback if none found
            if (empty($supervisedSectionIds)) {
                $supervisedSectionIds = Section::take(3)->pluck('id')->toArray();
            }
        }

        // 2. Team Workload Matrix
        $teamMembers = Member::whereHas('sections', fn($q) => $q->whereIn('sections.id', $supervisedSectionIds))
            ->with(['user', 'memberRoles', 'sections'])
            ->get()
            ->map(function (Member $m) use ($today) {
                $subtasks = SubTask::where('member_id', $m->id)->get();
                $activeTasks = $subtasks->whereIn('status', ['pending', 'in_progress']);
                $activeCount = $activeTasks->count();

                $urgentCount = $activeTasks->filter(function ($st) use ($today) {
                    $startDate = $st->start_date ? Carbon::parse($st->start_date)->startOfDay() : null;
                    $dueDate = $startDate ? $startDate->copy()->addDays($st->duration) : null;
                    return ($st->priority === 'urgent') || ($dueDate && $today->greaterThan($dueDate));
                })->count();

                $overdueCount = $subtasks->filter(function ($st) use ($today) {
                    if ($st->status === 'completed' || !$st->start_date) return false;
                    $dueDate = Carbon::parse($st->start_date)->startOfDay()->addDays($st->duration);
                    return $today->greaterThan($dueDate);
                })->count();

                $completedCount = $subtasks->where('status', 'completed')->count();

                // Capacity percentage (baseline: 10 active tasks = 100%)
                $loadPercentage = min(150, (int)round(($activeCount / 10) * 100));

                $workloadState = match(true) {
                    $loadPercentage > 100 => 'Overloaded',
                    $loadPercentage >= 80 => 'High Load',
                    $loadPercentage >= 40 => 'Normal',
                    default => 'Available',
                };

                return [
                    'id' => $m->id,
                    'user_id' => $m->user?->id,
                    'name' => $m->user?->name ?? 'Team Member',
                    'email' => $m->user?->email,
                    'avatar' => $m->user?->profile_photo_url,
                    'role' => $m->memberRoles->pluck('name')->first() ?? 'Staff',
                    'sections' => $m->sections->pluck('name')->toArray(),
                    'active_task_count' => $activeCount,
                    'urgent_task_count' => $urgentCount,
                    'overdue_task_count' => $overdueCount,
                    'completed_task_count' => $completedCount,
                    'load_percentage' => $loadPercentage,
                    'workload_state' => $workloadState,
                ];
            })->sortByDesc('load_percentage')->values()->toArray();

        // 3. Pending Approvals / Reviews (Subtasks with status 'submitted')
        $pendingApprovals = SubTask::where('status', 'submitted')
            ->whereHas('task.taskBoard', fn($q) => $q->whereIn('section_id', $supervisedSectionIds))
            ->with(['task.taskBoard.section', 'member.user'])
            ->latest('updated_at')
            ->get()
            ->map(function (SubTask $subTask) {
                return [
                    'id' => $subTask->id,
                    'name' => $subTask->name,
                    'details' => $subTask->details,
                    'deliverables' => $subTask->deliverables,
                    'duration' => $subTask->duration,
                    'priority' => $subTask->priority ?: 'medium',
                    'submitted_by' => $subTask->member?->user?->name ?? 'Team Member',
                    'submitted_by_avatar' => $subTask->member?->user?->profile_photo_url,
                    'submitted_date' => $subTask->updated_at ? $subTask->updated_at->format('M d, Y') : 'Recent',
                    'task_board_id' => $subTask->task?->taskBoard?->id,
                    'task_board_name' => $subTask->task?->taskBoard?->name,
                    'parent_task_name' => $subTask->task?->name,
                ];
            })->values()->toArray();

        // 4. Team Milestones / Task Boards
        $teamMilestones = TaskBoard::whereIn('section_id', $supervisedSectionIds)
            ->with(['section', 'workflows', 'tasks.subTasks'])
            ->get()
            ->map(function (TaskBoard $board) use ($today) {
                $subtasks = $board->tasks->flatMap->subTasks;
                $total = $subtasks->count();
                $completed = $subtasks->where('status', 'completed')->count();
                $progress = $total > 0 ? round(($completed / $total) * 100) : 0;

                $endDate = $board->end_date ? Carbon::parse($board->end_date)->startOfDay() : null;
                $isOverdue = $endDate && $today->greaterThan($endDate) && $board->status !== 'completed';
                $daysRemaining = $endDate ? $today->diffInDays($endDate, false) : null;

                // Determine active workflow phase
                $activeWorkflow = $board->workflows->sortBy('order')->first(function ($wf) use ($board) {
                    $tasksInWf = $board->tasks->where('workflow_id', $wf->id);
                    $subtasksInWf = $tasksInWf->flatMap->subTasks;
                    return $subtasksInWf->contains(fn($st) => $st->status !== 'completed');
                }) ?? $board->workflows->last();

                return [
                    'id' => $board->id,
                    'name' => $board->name,
                    'description' => $board->description,
                    'status' => $board->status,
                    'section_name' => $board->section?->name,
                    'progress' => $progress,
                    'total_tasks' => $total,
                    'completed_tasks' => $completed,
                    'current_phase' => $activeWorkflow?->name ?? 'General Delivery',
                    'end_date' => $endDate ? $endDate->format('M d, Y') : 'No deadline',
                    'is_overdue' => $isOverdue,
                    'days_remaining' => $daysRemaining,
                ];
            })->values()->toArray();

        // 5. Escalation Alert Box (Blocked tasks + overdue tasks)
        $escalationAlerts = SubTask::where(function ($q) use ($today) {
                $q->where('is_blocked', true)
                  ->orWhere(function ($oq) use ($today) {
                      $oq->where('status', '!=', 'completed')
                         ->whereNotNull('start_date');
                  });
            })
            ->whereHas('task.taskBoard', fn($q) => $q->whereIn('section_id', $supervisedSectionIds))
            ->with(['task.taskBoard.section', 'member.user'])
            ->get()
            ->filter(function (SubTask $subTask) use ($today) {
                if ($subTask->is_blocked) return true;
                if (!$subTask->start_date) return false;
                $dueDate = Carbon::parse($subTask->start_date)->startOfDay()->addDays($subTask->duration);
                // Exceeding by at least 1 day
                return $today->greaterThan($dueDate);
            })
            ->map(function (SubTask $subTask) use ($today) {
                $startDate = $subTask->start_date ? Carbon::parse($subTask->start_date)->startOfDay() : null;
                $dueDate = $startDate ? $startDate->copy()->addDays($subTask->duration) : null;
                $daysOverdue = ($dueDate && $today->greaterThan($dueDate)) ? $today->diffInDays($dueDate) : 0;

                return [
                    'id' => $subTask->id,
                    'name' => $subTask->name,
                    'parent_task_name' => $subTask->task?->name,
                    'task_board_id' => $subTask->task?->taskBoard?->id,
                    'task_board_name' => $subTask->task?->taskBoard?->name,
                    'owner_name' => $subTask->member?->user?->name ?? 'Unassigned',
                    'owner_avatar' => $subTask->member?->user?->profile_photo_url,
                    'is_blocked' => (bool)$subTask->is_blocked,
                    'blocked_reason' => $subTask->blocked_reason ?: ($daysOverdue > 0 ? "Exceeded deadline by {$daysOverdue} days" : 'Stalled progress'),
                    'days_overdue' => $daysOverdue,
                    'status' => $subTask->status,
                    'priority' => $subTask->priority ?: 'high',
                ];
            })
            ->sortByDesc(fn($item) => $item['is_blocked'] ? 100 + $item['days_overdue'] : $item['days_overdue'])
            ->values()
            ->take(8)
            ->toArray();

        return [
            'team_workload' => $teamMembers,
            'pending_approvals' => $pendingApprovals,
            'team_milestones' => $teamMilestones,
            'escalation_alerts' => $escalationAlerts,
            'supervised_sections' => Section::whereIn('id', $supervisedSectionIds)->get(['id', 'name'])->toArray(),
        ];
    }

    /**
     * Build Department Head Dashboard dataset.
     */
    public static function getDepartmentHeadData(User $user): array
    {
        $today = Carbon::now()->startOfDay();

        // 1. Department Scope
        $deptIds = SystemRuleEvaluator::getUserDepartmentIds($user);
        if ($user->hasRole('admin') || empty($deptIds)) {
            $deptIds = Department::pluck('id')->toArray();
        }

        $departments = Department::whereIn('id', $deptIds)->with('sections')->get();
        $deptSectionIds = Section::whereIn('department_id', $deptIds)->pluck('id')->toArray();

        // 2. Executive Summary Metrics
        $boards = TaskBoard::whereIn('section_id', $deptSectionIds)->with('tasks.subTasks')->get();

        $activeBoardsCount = $boards->where('status', 'active')->count();
        $completedBoardsCount = $boards->where('status', 'completed')->count();
        $totalBoardsCount = $boards->count();

        $allSubtasks = $boards->flatMap->tasks->flatMap->subTasks;
        $totalSubtasks = $allSubtasks->count();
        $completedSubtasks = $allSubtasks->where('status', 'completed')->count();
        $completionRate = $totalSubtasks > 0 ? round(($completedSubtasks / $totalSubtasks) * 100) : 0;

        // Timeline Health
        $onTrackCount = 0;
        $atRiskCount = 0;
        $delayedCount = 0;

        foreach ($boards as $b) {
            $endDate = $b->end_date ? Carbon::parse($b->end_date)->startOfDay() : null;
            $bSubtasks = $b->tasks->flatMap->subTasks;
            $bTotal = $bSubtasks->count();
            $bCompleted = $bSubtasks->where('status', 'completed')->count();
            $bProgress = $bTotal > 0 ? ($bCompleted / $bTotal) : 0;

            if ($b->status === 'completed') {
                $onTrackCount++;
                continue;
            }

            if ($endDate && $today->greaterThan($endDate)) {
                $delayedCount++;
            } elseif ($endDate && $today->diffInDays($endDate) <= 14 && $bProgress < 0.6) {
                $atRiskCount++;
            } else {
                $onTrackCount++;
            }
        }

        $executiveSummary = [
            'active_task_boards' => $activeBoardsCount,
            'completed_task_boards' => $completedBoardsCount,
            'total_task_boards' => $totalBoardsCount,
            'department_completion_rate' => $completionRate,
            'total_subtasks' => $totalSubtasks,
            'completed_subtasks' => $completedSubtasks,
            'timeline_health' => [
                'on_track' => $onTrackCount,
                'at_risk' => $atRiskCount,
                'delayed' => $delayedCount,
            ],
            // As per instruction 4.1: Do not fabricate financial data.
            'budget_health' => null,
        ];

        // 3. Section-by-Section Performance
        $sections = Section::whereIn('id', $deptSectionIds)->with(['department', 'projectManager.user', 'taskBoards.tasks.subTasks'])->get();

        $sectionPerformance = $sections->map(function (Section $sec) use ($today) {
            $subtasks = $sec->taskBoards->flatMap->tasks->flatMap->subTasks;
            $total = $subtasks->count();
            $completed = $subtasks->where('status', 'completed')->count();
            $active = $subtasks->whereIn('status', ['pending', 'in_progress'])->count();

            $overdue = $subtasks->filter(function ($st) use ($today) {
                if ($st->status === 'completed' || !$st->start_date) return false;
                $dueDate = Carbon::parse($st->start_date)->startOfDay()->addDays($st->duration);
                return $today->greaterThan($dueDate);
            })->count();

            $rate = $total > 0 ? round(($completed / $total) * 100) : 0;

            return [
                'id' => $sec->id,
                'name' => $sec->name,
                'department_name' => $sec->department?->name ?? 'Department',
                'manager_name' => $sec->projectManager?->user?->name ?? 'Assigned Section Head',
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'active_tasks' => $active,
                'overdue_tasks' => $overdue,
                'completion_rate' => $rate,
                'task_boards_count' => $sec->taskBoards->count(),
            ];
        })->values()->toArray();

        // 4. Milestone Roadmap / Macro Gantt View
        $roadmap = $boards->map(function (TaskBoard $b) use ($today) {
            $startDate = $b->start_date ? Carbon::parse($b->start_date)->format('Y-m-d') : null;
            $endDate = $b->end_date ? Carbon::parse($b->end_date)->format('Y-m-d') : null;
            $subtasks = $b->tasks->flatMap->subTasks;
            $total = $subtasks->count();
            $completed = $subtasks->where('status', 'completed')->count();
            $progress = $total > 0 ? round(($completed / $total) * 100) : 0;

            return [
                'id' => $b->id,
                'name' => $b->name,
                'status' => $b->status,
                'section_name' => $b->section?->name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'progress' => $progress,
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'is_delayed' => $endDate && $today->greaterThan(Carbon::parse($endDate)) && $b->status !== 'completed',
            ];
        })->sortBy('start_date')->values()->toArray();

        return [
            'executive_summary' => $executiveSummary,
            'section_performance' => $sectionPerformance,
            'department_roadmap' => $roadmap,
            'departments' => $departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name])->values()->toArray(),
        ];
    }

    /**
     * Build Administrator Dashboard dataset.
     */
    public static function getAdminData(User $user): array
    {
        // 1. System Status & Health Grid
        $dbStart = microtime(true);
        DB::select('SELECT 1');
        $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);

        $diskPath = storage_path();
        $freeDiskBytes = disk_free_space($diskPath);
        $totalDiskBytes = disk_total_space($diskPath);
        $diskFreeGb = $freeDiskBytes !== false ? round($freeDiskBytes / 1024 / 1024 / 1024, 2) : 0;
        $diskTotalGb = $totalDiskBytes !== false ? round($totalDiskBytes / 1024 / 1024 / 1024, 2) : 0;

        $failedJobsCount = DB::table('failed_jobs')->count();
        $activeSessions = DB::table('sessions')->count();
        $twoFactorCount = User::whereNotNull('two_factor_secret')->count();
        $ssoCount = DB::table('sso_identity_links')->count();

        $systemHealth = [
            'server' => [
                'name' => 'Application Server',
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'server_time' => Carbon::now()->format('Y-m-d H:i:s T'),
                'status' => 'healthy',
            ],
            'database' => [
                'name' => 'Database Engine',
                'driver' => DB::connection()->getDriverName(),
                'database_name' => DB::connection()->getDatabaseName(),
                'latency_ms' => $dbLatency,
                'status' => $dbLatency < 100 ? 'healthy' : 'warning',
            ],
            'storage' => [
                'name' => 'Storage Volume',
                'free_space' => $diskFreeGb . ' GB',
                'total_space' => $diskTotalGb . ' GB',
                'is_writable' => is_writable($diskPath),
                'status' => (is_writable($diskPath) && $diskFreeGb > 2) ? 'healthy' : 'warning',
            ],
            'security' => [
                'name' => 'Security & Auth Services',
                'active_sessions' => $activeSessions,
                'two_factor_users' => $twoFactorCount,
                'sso_linked_users' => $ssoCount,
                'status' => 'healthy',
            ],
            'jobs_queue' => [
                'name' => 'Job Queue Worker',
                'failed_jobs' => $failedJobsCount,
                'status' => $failedJobsCount === 0 ? 'healthy' : ($failedJobsCount < 10 ? 'warning' : 'critical'),
            ],
        ];

        // 2. User & Role Management Quick View
        $usersList = User::with(['role', 'member.memberRoles', 'member.sections.department'])
            ->latest('id')
            ->take(12)
            ->get()
            ->map(function (User $u) {
                $member = $u->member;
                $functionalRoles = $member ? $member->memberRoles->pluck('name')->toArray() : [];
                $sections = $member ? $member->sections->pluck('name')->toArray() : [];
                $departments = $member ? $member->sections->pluck('department.name')->filter()->unique()->toArray() : [];

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'username' => $u->username,
                    'system_role' => $u->role?->name ?? 'User',
                    'system_role_slug' => $u->role?->slug ?? 'user',
                    'functional_roles' => $functionalRoles,
                    'sections' => $sections,
                    'departments' => array_values($departments),
                    'created_at' => $u->created_at?->format('M d, Y') ?? 'N/A',
                ];
            })->values()->toArray();

        // 3. Security & Audit Logs (from system_logs table)
        $auditLogs = SystemLog::latest('id')
            ->take(15)
            ->get()
            ->map(function (SystemLog $log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'actor' => $log->user_name ?? 'System',
                    'action' => $log->action,
                    'description' => $log->description,
                    'ip_address' => $log->ip_address ?? '127.0.0.1',
                    'time_ago' => $log->created_at?->diffForHumans() ?? 'Just now',
                    'created_at' => $log->created_at?->format('Y-m-d H:i:s') ?? '',
                ];
            })->values()->toArray();

        $totalLogsCount = SystemLog::count();

        // 4. Global Lookup / System Configuration Summary
        $systemConfiguration = [
            [
                'name' => 'Departments',
                'description' => 'Organizational division units',
                'usage_count' => Department::count(),
                'status' => 'Active',
                'route' => 'admin.management',
            ],
            [
                'name' => 'Sections',
                'description' => 'Team work sections',
                'usage_count' => Section::count(),
                'status' => 'Active',
                'route' => 'admin.management',
            ],
            [
                'name' => 'System Rules',
                'description' => 'Access control & workflow guards',
                'usage_count' => SystemRule::where('enabled', true)->count() . ' / ' . SystemRule::count(),
                'status' => 'Configured',
                'route' => 'admin.settings',
            ],
            [
                'name' => 'Functional Roles',
                'description' => 'Member operational roles',
                'usage_count' => MemberRole::count(),
                'status' => 'Active',
                'route' => 'admin.management',
            ],
            [
                'name' => 'Workflow Types',
                'description' => 'Kanban, Waterfall & Lifecycle flows',
                'usage_count' => WorkflowType::count(),
                'status' => 'Active',
                'route' => 'admin.management',
            ],
        ];

        return [
            'system_health' => $systemHealth,
            'user_management' => $usersList,
            'total_users_count' => User::count(),
            'audit_logs' => $auditLogs,
            'total_audit_logs' => $totalLogsCount,
            'system_configuration' => $systemConfiguration,
        ];
    }
}
