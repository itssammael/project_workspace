<?php

namespace App\Services;

use App\Models\User;
use App\Models\TaskBoard;
use App\Models\Task;
use App\Models\SubTask;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Retrieve all personal and system notifications for the given user.
     */
    public static function getNotifications(User $user): array
    {
        $member = $user->member;
        $memberId = $member?->id;
        $today = now()->startOfDay();

        // Check if user has Functional Role "Project Manager"
        $isProjectManager = false;
        if ($member) {
            $isProjectManager = $member->memberRoles()
                ->whereIn('slug', ['project_manager', 'project-manager'])
                ->exists();

            if (!$isProjectManager) {
                // Also check if member is assigned as Section Project Manager
                $isProjectManager = \App\Models\Section::where('member_id', $member->id)->exists();
            }
        }

        // Fetch read notification keys
        $readKeys = [];
        if (Schema::hasTable('user_notification_reads')) {
            $readKeys = DB::table('user_notification_reads')
                ->where('user_id', $user->id)
                ->pluck('notification_key')
                ->toArray();
        }

        $notifications = [];

        // ==========================================
        // 1. PERSONAL NOTIFICATIONS
        // ==========================================

        if ($memberId) {
            // A. Upcoming Assigned Tasks & Overdue Tasks & Task Assignments
            // Get subtasks assigned to the user
            $assignedSubtasks = SubTask::with(['task.taskBoard', 'task.workflow'])
                ->where('member_id', $memberId)
                ->get();

            foreach ($assignedSubtasks as $st) {
                $task = $st->task;
                if (!$task || !$task->taskBoard) {
                    continue;
                }

                $board = $task->taskBoard;
                $workflowName = $task->workflow?->name ?? 'General';
                $taskDisplayName = $st->name ?: $task->name;

                // Check Due Date & Count Down
                if ($st->start_date && $st->status !== 'completed') {
                    $startDate = Carbon::parse($st->start_date)->startOfDay();
                    $duration = max(1, (int)$st->duration);
                    $dueDate = $startDate->copy()->addDays($duration);
                    $diffDays = (int) $today->diffInDays($dueDate, false);

                    // Upcoming Assigned Task: 3 days before due date down to today
                    if ($diffDays >= 0 && $diffDays <= 3) {
                        $dueText = $diffDays === 0 ? 'today' : ($diffDays === 1 ? '1 day' : "{$diffDays} days");
                        $key = "upcoming_subtask_{$st->id}_{$diffDays}";
                        
                        $notifications[] = [
                            'id' => $key,
                            'type' => 'personal',
                            'category' => 'upcoming_task',
                            'title' => 'Upcoming Assigned Task',
                            // Exact format: "Task: {task/subtask name} for {task board title}:{task workflow name} will be due in {3 days / today}"
                            'message' => "Task: {$taskDisplayName} for {$board->name}:{$workflowName} will be due in {$dueText}",
                            'task_board_id' => $board->id,
                            'task_board_name' => $board->name,
                            'task_id' => $task->id,
                            'sub_task_id' => $st->id,
                            'workflow_name' => $workflowName,
                            'due_date' => $dueDate->format('M d, Y'),
                            'diff_days' => $diffDays,
                            'badge' => $diffDays === 0 ? 'Due Today' : "Due in {$diffDays}d",
                            'urgency' => $diffDays === 0 ? 'critical' : ($diffDays === 1 ? 'high' : 'medium'),
                            'icon' => 'clock',
                            'created_at' => $st->updated_at->toIso8601String(),
                            'read' => in_array($key, $readKeys),
                        ];
                    }
                    // Overdue Task
                    elseif ($diffDays < 0) {
                        $daysOverdue = abs($diffDays);
                        $key = "overdue_subtask_{$st->id}";
                        
                        $notifications[] = [
                            'id' => $key,
                            'type' => 'personal',
                            'category' => 'overdue_task',
                            'title' => 'Overdue Task',
                            'message' => "Task: {$taskDisplayName} for {$board->name}:{$workflowName} is overdue by {$daysOverdue} " . ($daysOverdue === 1 ? 'day' : 'days'),
                            'task_board_id' => $board->id,
                            'task_board_name' => $board->name,
                            'task_id' => $task->id,
                            'sub_task_id' => $st->id,
                            'workflow_name' => $workflowName,
                            'due_date' => $dueDate->format('M d, Y'),
                            'days_overdue' => $daysOverdue,
                            'badge' => "Overdue ({$daysOverdue}d)",
                            'urgency' => 'critical',
                            'icon' => 'exclamation-circle',
                            'created_at' => $st->updated_at->toIso8601String(),
                            'read' => in_array($key, $readKeys),
                        ];
                    }
                }

                // Task Assignment (only show tasks assigned to you by others or the Project Manager)
                // Filter: only show if the board manager or creator is not this user (or if assigned to user)
                $isBoardManager = $board->section?->member_id === $memberId;
                if (!$isBoardManager) {
                    $key = "task_assigned_{$st->id}";
                    $notifications[] = [
                        'id' => $key,
                        'type' => 'personal',
                        'category' => 'task_assignment',
                        'title' => 'Task Assignment',
                        'message' => "You were assigned to task: {$taskDisplayName} in {$board->name} ({$workflowName})",
                        'task_board_id' => $board->id,
                        'task_board_name' => $board->name,
                        'task_id' => $task->id,
                        'sub_task_id' => $st->id,
                        'workflow_name' => $workflowName,
                        'badge' => 'Assigned',
                        'urgency' => 'info',
                        'icon' => 'user-plus',
                        'created_at' => $st->created_at->toIso8601String(),
                        'read' => in_array($key, $readKeys),
                    ];
                }
            }

            // B. Task Submissions (only for Functional Role: Project manager and tasks with submitted status)
            if ($isProjectManager) {
                // Find all tasks or subtasks with status 'submitted'
                $submittedSubtasks = SubTask::with(['task.taskBoard', 'task.workflow', 'member.user'])
                    ->where('status', 'submitted')
                    ->get();

                foreach ($submittedSubtasks as $st) {
                    $task = $st->task;
                    if (!$task || !$task->taskBoard) continue;

                    $board = $task->taskBoard;
                    $workflowName = $task->workflow?->name ?? 'General';
                    $taskDisplayName = $st->name ?: $task->name;
                    $submitterName = $st->member?->user?->name ?? 'A team member';

                    $key = "task_submitted_{$st->id}";
                    $notifications[] = [
                        'id' => $key,
                        'type' => 'personal',
                        'category' => 'task_submission',
                        'title' => 'Task Submission for Review',
                        'message' => "Task: {$taskDisplayName} for {$board->name}:{$workflowName} was submitted for review by {$submitterName}",
                        'task_board_id' => $board->id,
                        'task_board_name' => $board->name,
                        'task_id' => $task->id,
                        'sub_task_id' => $st->id,
                        'workflow_name' => $workflowName,
                        'badge' => 'Submitted',
                        'urgency' => 'high',
                        'icon' => 'check-badge',
                        'created_at' => $st->updated_at->toIso8601String(),
                        'read' => in_array($key, $readKeys),
                    ];
                }
            }

            // C. Task Board Assignment / Removal (includes collaboration)
            $boardMemberships = DB::table('task_board_members')
                ->join('task_boards', 'task_board_members.task_board_id', '=', 'task_boards.id')
                ->leftJoin('member_roles', 'task_board_members.member_role_id', '=', 'member_roles.id')
                ->where('task_board_members.member_id', $memberId)
                ->select(
                    'task_board_members.task_board_id',
                    'task_board_members.created_at',
                    'task_boards.name as board_name',
                    'member_roles.name as role_name'
                )
                ->get();

            foreach ($boardMemberships as $bm) {
                $key = "board_assignment_{$bm->task_board_id}_{$memberId}";
                $roleLabel = $bm->role_name ? " as {$bm->role_name}" : '';
                $notifications[] = [
                    'id' => $key,
                    'type' => 'personal',
                    'category' => 'board_assignment',
                    'title' => 'Task Board Assignment',
                    'message' => "You were added to task board '{$bm->board_name}'{$roleLabel}",
                    'task_board_id' => $bm->task_board_id,
                    'task_board_name' => $bm->board_name,
                    'badge' => 'Board Role',
                    'urgency' => 'info',
                    'icon' => 'user-group',
                    'created_at' => $bm->created_at ? Carbon::parse($bm->created_at)->toIso8601String() : now()->toIso8601String(),
                    'read' => in_array($key, $readKeys),
                ];
            }
        }

        // D. Created Task Boards
        $createdBoards = TaskBoard::query()
            ->latest('created_at')
            ->limit(10)
            ->get();

        foreach ($createdBoards as $cb) {
            $key = "board_created_{$cb->id}";
            $notifications[] = [
                'id' => $key,
                'type' => 'personal',
                'category' => 'board_created',
                'title' => 'Created Task Board',
                'message' => "Task board '{$cb->name}' was created",
                'task_board_id' => $cb->id,
                'task_board_name' => $cb->name,
                'badge' => 'New Board',
                'urgency' => 'info',
                'icon' => 'squares-plus',
                'created_at' => $cb->created_at->toIso8601String(),
                'read' => in_array($key, $readKeys),
            ];
        }

        // E. Profile Changes (e.g. password, username, email)
        $profileLogs = SystemLog::query()
            ->where('user_id', $user->id)
            ->whereIn('action', ['Update Profile', 'Update Password', 'Update User'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        foreach ($profileLogs as $pl) {
            $key = "profile_change_{$pl->id}";
            $notifications[] = [
                'id' => $key,
                'type' => 'personal',
                'category' => 'profile_change',
                'title' => 'Profile Change',
                'message' => $pl->description,
                'badge' => 'Security',
                'urgency' => 'info',
                'icon' => 'shield-check',
                'action_url' => route('profile.show'),
                'created_at' => $pl->created_at->toIso8601String(),
                'read' => in_array($key, $readKeys),
            ];
        }

        // ==========================================
        // 2. SYSTEM NOTIFICATIONS (System-wide changes)
        // ==========================================
        $systemWideActions = [
            'Update Settings',
            'Create System Rule',
            'Update System Rule',
            'Toggle System Rule',
            'Delete System Rule',
            'Import System Rules',
            'Create Role',
            'Update Role',
            'Delete Role',
            'Update Role Switcher',
            'Create Department',
            'Update Department',
            'Delete Department',
            'Create Section',
            'Update Section',
            'Delete Section',
            'Create Workflow',
            'Update Workflow',
            'Delete Workflow',
            'Create Workflow Type',
            'Update Workflow Type',
            'Bulk Assign Workflows',
            'Create Member Role',
            'Update Member Role',
        ];

        $systemLogs = SystemLog::query()
            ->whereIn('action', $systemWideActions)
            ->latest('created_at')
            ->limit(15)
            ->get();

        foreach ($systemLogs as $sl) {
            $key = "system_log_{$sl->id}";
            $notifications[] = [
                'id' => $key,
                'type' => 'system',
                'category' => 'system_update',
                'title' => $sl->action,
                'message' => $sl->description,
                'performed_by' => $sl->user_name,
                'badge' => 'System',
                'urgency' => 'info',
                'icon' => 'cog',
                'created_at' => $sl->created_at->toIso8601String(),
                'read' => in_array($key, $readKeys),
            ];
        }

        // Sort all notifications by created_at descending
        usort($notifications, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        // Compute summary counts
        $unreadCount = count(array_filter($notifications, fn($n) => !$n['read']));
        $personalCount = count(array_filter($notifications, fn($n) => $n['type'] === 'personal'));
        $systemCount = count(array_filter($notifications, fn($n) => $n['type'] === 'system'));

        return [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'personal_count' => $personalCount,
            'system_count' => $systemCount,
            'total_count' => count($notifications),
        ];
    }

    /**
     * Mark a specific notification key as read for the user.
     */
    public static function markAsRead(User $user, string $notificationKey): void
    {
        if (!Schema::hasTable('user_notification_reads')) {
            return;
        }

        DB::table('user_notification_reads')->updateOrInsert(
            ['user_id' => $user->id, 'notification_key' => $notificationKey],
            ['read_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /**
     * Mark all notifications as read for the user.
     */
    public static function markAllAsRead(User $user, array $notificationKeys): void
    {
        if (!Schema::hasTable('user_notification_reads') || empty($notificationKeys)) {
            return;
        }

        $records = [];
        $now = now();
        foreach ($notificationKeys as $key) {
            $records[] = [
                'user_id' => $user->id,
                'notification_key' => $key,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('user_notification_reads')->upsert(
            $records,
            ['user_id', 'notification_key'],
            ['read_at', 'updated_at']
        );
    }
}
