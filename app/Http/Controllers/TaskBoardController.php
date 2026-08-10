<?php

namespace App\Http\Controllers;

use App\Models\TaskBoard;
use App\Models\Section;
use App\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class TaskBoardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $member = $user->member;

        if ($user->hasRole('admin')) {
            $taskBoardsQuery = TaskBoard::with(['section.projectManager.user', 'tasks.subTasks']);
        } else {
            $sectionIds = $member ? $member->sections->pluck('id')->toArray() : [];
            $taskBoardsQuery = TaskBoard::whereIn('section_id', $sectionIds)->with(['section.projectManager.user', 'tasks.subTasks']);
        }

        $taskBoards = $taskBoardsQuery->get()->map(function (TaskBoard $taskBoard) {
            $subTasks = $taskBoard->tasks->flatMap->subTasks;
            $totalTasks = $subTasks->count();
            $completedTasks = $subTasks->where('status', 'completed')->count();
            
            $taskBoard->total_tasks = $totalTasks;
            $taskBoard->completed_tasks = $completedTasks;
            $taskBoard->progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
            return $taskBoard;
        });

        $canCreateTaskBoards = Gate::allows('create-task-boards') || Gate::allows('create-projects');
        $canDeleteTaskBoards = Gate::allows('delete-task-board') || Gate::allows('delete-project');

        return Inertia::render('TaskBoard/Index', [
            'taskBoards' => $taskBoards,
            'projects' => $taskBoards,
            'canCreateTaskBoards' => $canCreateTaskBoards,
            'canCreateProjects' => $canCreateTaskBoards,
            'canDeleteTaskBoards' => $canDeleteTaskBoards,
            'canDeleteProjects' => $canDeleteTaskBoards,
        ]);
    }

    public function show(Request $request, TaskBoard $taskBoard): Response
    {
        if (Gate::has('view-task-board')) {
            Gate::authorize('view-task-board', $taskBoard);
        } else {
            Gate::authorize('view-project', $taskBoard);
        }

        // Load section, assigned members, tasks and workflows
        $taskBoard->load(['section.projectManager.user', 'section.members.user', 'members.user', 'members.memberRoles']);

        $taskBoard->members->transform(function ($m) {
            $projectRole = \App\Models\MemberRole::find($m->pivot->member_role_id);
            $m->project_role = $projectRole ? $projectRole->name : 'Member';
            return $m;
        });

        // TaskBoard-level progress
        $taskIds = $taskBoard->tasks()->pluck('id');
        $totalTasks = \App\Models\SubTask::whereIn('task_id', $taskIds)->count();
        $completedTasks = \App\Models\SubTask::whereIn('task_id', $taskIds)->where('status', 'completed')->count();
        $taskBoard->total_tasks = $totalTasks;
        $taskBoard->completed_tasks = $completedTasks;
        $taskBoard->progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        // Fetch all tasks for the task board with their subtasks up front
        $allTasks = $taskBoard->tasks()->with(['subTasks.member.user'])->get();

        // Populate tasks with computed attributes
        $allTasks->transform(function ($task) use ($taskBoard, $request) {
            $subTasks = $task->subTasks;
            if ($subTasks->isNotEmpty()) {
                $task->deliverables = $subTasks->pluck('deliverables')->filter()->implode(', ');
                
                $earliestStart = null;
                $latestEnd = null;
                
                foreach ($subTasks as $st) {
                    if ($st->start_date) {
                        $stStart = \Carbon\Carbon::parse($st->start_date);
                        $stEnd = $stStart->copy()->addDays($st->duration);
                        
                        if (is_null($earliestStart) || $stStart->lessThan($earliestStart)) {
                            $earliestStart = $stStart;
                        }
                        if (is_null($latestEnd) || $stEnd->greaterThan($latestEnd)) {
                            $latestEnd = $stEnd;
                        }
                    }
                }
                
                if ($earliestStart && $latestEnd) {
                    $task->start_date = $earliestStart->format('Y-m-d');
                    $task->duration = $earliestStart->diffInDays($latestEnd);
                } else {
                    $task->start_date = $subTasks->min('start_date');
                    $task->duration = $subTasks->sum('duration');
                }
                
                $firstSubTask = $subTasks->first();
                $task->member_id = $firstSubTask ? $firstSubTask->member_id : null;
                
                if ($subTasks->every(fn($st) => $st->status === 'completed')) {
                    $task->status = 'completed';
                } else if ($subTasks->every(fn($st) => $st->status === 'pending')) {
                    $task->status = 'pending';
                } else if ($subTasks->every(fn($st) => $st->status === 'completed' || $st->status === 'submitted')) {
                    $task->status = 'submitted';
                } else {
                    $task->status = 'in_progress';
                }
                $task->member = $firstSubTask ? $firstSubTask->member : null;
            } else {
                $task->deliverables = null;
                $task->duration = 0;
                $task->member_id = null;
                $task->start_date = null;
                $task->status = 'pending';
                $task->member = null;
            }
            
            // Expose sub_tasks list to the frontend
            $mappedSubTasks = $subTasks->map(function ($st) use ($taskBoard, $request) {
                $projectMemberRole = \App\Models\MemberRole::find(
                    \Illuminate\Support\Facades\DB::table('task_board_members')
                        ->where('task_board_id', $taskBoard->id)
                        ->where('member_id', $st->member_id)
                        ->value('member_role_id')
                );

                $user = $request->user();
                $member = $user ? $user->member : null;
                
                $isAssignee = $member && $st->member_id === $member->id;
                
                $isPM = $member && $member->memberRoles()->where('slug', 'project_manager')->exists() && 
                        $taskBoard->section && 
                        $taskBoard->section->member_id === $member->id;

                $isDeptHead = $member && $member->memberRoles()->where('slug', 'department_head')->exists();
                
                $isAdmin = $user && $user->role_id === 1;

                $canComment = $isAssignee || $isPM || $isDeptHead || $isAdmin;

                $attachments = \App\Models\SubTaskAttachment::where('sub_task_id', $st->id)
                    ->latest()
                    ->get()
                    ->map(fn($att) => [
                        'id' => $att->id,
                        'attachment_type' => $att->attachment_type,
                        'attachment' => $att->attachment,
                    ]);

                $comments = $canComment 
                    ? \App\Models\SubTaskComment::where('sub_task_id', $st->id)
                        ->with('member.user')
                        ->latest()
                        ->get()
                        ->map(fn($c) => [
                            'id' => $c->id,
                            'comment' => $c->comment,
                            'created_at' => $c->created_at->toIso8601String(),
                            'member' => [
                                'id' => $c->member->id,
                                'name' => $c->member->user->name,
                            ]
                        ])
                    : [];

                return [
                    'id' => $st->id,
                    'name' => $st->name,
                    'details' => $st->details,
                    'deliverables' => $st->deliverables,
                    'duration' => $st->duration,
                    'member_id' => $st->member_id,
                    'start_date' => $st->start_date ? $st->start_date->format('Y-m-d') : null,
                    'status' => $st->status,
                    'member' => $st->member ? [
                        'id' => $st->member->id,
                        'name' => $st->member->user->name,
                        'role' => $projectMemberRole ? $projectMemberRole->name : 'Member',
                    ] : null,
                    'can_comment' => $canComment,
                    'attachments' => $attachments,
                    'comments' => $comments,
                ];
            });
            $task->setRelation('subTasks', $mappedSubTasks);
            $task->sub_tasks = $mappedSubTasks;
            return $task;
        });

        // Load the task board's workflows and identify which ones belong to the "Kanban" workflow type
        $workflowsList = $taskBoard->workflows()->with('workflowType')->orderBy('order', 'asc')->get();
        $kanbanWorkflowIds = $workflowsList->filter(fn($w) => $w->workflowType?->name === 'Kanban')->pluck('id')->toArray();

        $workflows = $workflowsList->map(function ($workflow) use ($allTasks, $kanbanWorkflowIds) {
            $isKanban = $workflow->workflowType?->name === 'Kanban';
            
            if ($isKanban) {
                $nameLower = strtolower($workflow->name);
                $targetStatus = 'pending';
                if ($nameLower === 'to do' || $nameLower === 'to-do' || $nameLower === 'todo') {
                    $targetStatus = 'pending';
                } else if ($nameLower === 'doing') {
                    $targetStatus = 'in_progress';
                } else if ($nameLower === 'submitted') {
                    $targetStatus = 'submitted';
                } else if ($nameLower === 'completed' || $nameLower === 'done') {
                    $targetStatus = 'completed';
                }

                $tasks = $allTasks->filter(function ($task) use ($workflow, $kanbanWorkflowIds, $targetStatus) {
                    $directlyInWorkflow = $task->workflow_id === $workflow->id;
                    $isUserSelectedWorkflow = !in_array($task->workflow_id, $kanbanWorkflowIds);
                    $matchesStatus = $task->status === $targetStatus;
                    
                    return $directlyInWorkflow || ($isUserSelectedWorkflow && $matchesStatus);
                })->values();
            } else {
                $tasks = $allTasks->filter(function ($task) use ($workflow) {
                    return $task->workflow_id === $workflow->id;
                })->values();
            }

            $workflow->tasks = $tasks;

            $workflowTotal = $tasks->count();
            $workflowCompleted = $tasks->where('status', 'completed')->count();
            $workflow->setAttribute('total_tasks', $workflowTotal);
            $workflow->setAttribute('completed_tasks', $workflowCompleted);
            $workflow->setAttribute('progress', $workflowTotal > 0 ? round(($workflowCompleted / $workflowTotal) * 100) : 0);
            $workflow->setAttribute('workflow_type', $workflow->workflowType?->name ?? 'General');

            return $workflow;
        });

        // Restrict assignable members to members assigned to this specific task board
        $teamMembers = $taskBoard->members->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'role' => $m->project_role,
            ];
        });

        $canUpdate = Gate::allows('update-task-board', $taskBoard) || Gate::allows('update-project', $taskBoard);

        $sections = $canUpdate
            ? Section::with(['members.user', 'members.memberRoles'])->get()->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'members' => $t->members->map(fn($m) => [
                        'id' => $m->id,
                        'name' => $m->user->name,
                        'member_roles' => $m->memberRoles->map(fn($mr) => [
                            'id' => $mr->id,
                            'name' => $mr->name
                        ])
                    ])
                ];
            }) 
            : [];

        $projectAttachments = \App\Models\SubTaskAttachment::whereIn('sub_task_id', function ($query) use ($taskIds) {
            $query->select('id')->from('sub_tasks')->whereIn('task_id', $taskIds);
        })->with('subTask.task')->latest()->get()->map(fn($att) => [
            'id' => $att->id,
            'attachment_type' => $att->attachment_type,
            'attachment' => $att->attachment,
            'sub_task_name' => $att->subTask->name,
            'task_name' => $att->subTask->task->name,
            'created_at' => $att->created_at->toIso8601String(),
        ]);

        return Inertia::render('TaskBoard/Show', [
            'taskBoard' => $taskBoard,
            'project' => $taskBoard,
            'workflows' => $workflows,
            'teamMembers' => $teamMembers,
            'canManageTasks' => Gate::allows('manage-tasks', $taskBoard),
            'canDeleteTaskBoard' => Gate::allows('delete-task-board') || Gate::allows('delete-project'),
            'canDeleteProject' => Gate::allows('delete-task-board') || Gate::allows('delete-project'),
            'canUpdateTaskBoard' => $canUpdate,
            'canUpdateProject' => $canUpdate,
            'sections' => $sections,
            'memberRoles' => \App\Models\MemberRole::all(),
            'currentMemberId' => $request->user()->member?->id,
            'projectAttachments' => $projectAttachments,
        ]);
    }

    public function create(): Response
    {
        if (Gate::has('create-task-boards')) {
            Gate::authorize('create-task-boards');
        } else {
            Gate::authorize('create-projects');
        }

        $sections = Section::with(['projectManager.user', 'members.user', 'members.memberRoles'])->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'project_manager' => $t->projectManager ? [
                    'id' => $t->projectManager->id,
                    'name' => $t->projectManager->user->name,
                ] : null,
                'members' => $t->members->map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->user->name,
                    'member_roles' => $m->memberRoles->map(fn($mr) => [
                        'id' => $mr->id,
                        'name' => $mr->name
                    ])
                ])
            ];
        });
        
        $workflows = Workflow::with('workflowType')->orderBy('order', 'asc')->get()->map(function ($workflow) {
            return [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'order' => $workflow->order,
                'workflow_type' => $workflow->workflowType?->name ?? 'General',
            ];
        });
        
        return Inertia::render('TaskBoard/Create', [
            'sections' => $sections,
            'workflows' => $workflows,
            'memberRoles' => \App\Models\MemberRole::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Gate::has('create-task-boards')) {
            Gate::authorize('create-task-boards');
        } else {
            Gate::authorize('create-projects');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string|in:planning,active,completed,on_hold',
            'section_id' => 'required|exists:sections,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'workflow_ids' => 'required|array|min:1',
            'workflow_ids.*' => 'exists:workflows,id',
            'members' => 'nullable|array',
            'members.*.id' => 'required|exists:members,id',
            'members.*.member_role_id' => 'required|exists:member_roles,id',
        ]);

        // Evaluate active System Rules for Task Board validation
        $validationRules = \App\Models\SystemRule::where('enabled', true)
            ->where('type', 'validation_rule')
            ->get();

        foreach ($validationRules as $rule) {
            $logic = $rule->rule_logic ?? [];
            if (($logic['field'] ?? '') === 'name' && isset($logic['min_length'])) {
                $minLength = (int)$logic['min_length'];
                if (mb_strlen($validated['name']) < $minLength) {
                    $msg = str_replace(
                        ['[Task Board Name]', '[Project Name]', '[Field Name]'],
                        [$validated['name'], $validated['name'], 'Task Board Name'],
                        $logic['error_message'] ?? "Task Board Name must be at least {$minLength} characters long."
                    );
                    return redirect()->back()->withErrors(['name' => $msg]);
                }
            }
        }

        $taskBoard = TaskBoard::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'section_id' => $validated['section_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        $taskBoard->workflows()->sync($validated['workflow_ids']);

        if (!empty($validated['members'])) {
            $syncData = [];
            foreach ($validated['members'] as $m) {
                $syncData[$m['id']] = ['member_role_id' => $m['member_role_id']];
            }
            $taskBoard->members()->sync($syncData);
        }

        \App\Models\SystemLog::log('Create Task Board', "Task Board '{$taskBoard->name}' was created.");

        return redirect()->route('dashboard')->with('success', 'Task Board created successfully.');
    }

    public function update(Request $request, TaskBoard $taskBoard): RedirectResponse
    {
        if (Gate::has('update-task-board')) {
            Gate::authorize('update-task-board', $taskBoard);
        } else {
            Gate::authorize('update-project', $taskBoard);
        }

        $validated = $request->validate([
            'section_id' => 'required|exists:sections,id',
            'members' => 'nullable|array',
            'members.*.id' => 'required|exists:members,id',
            'members.*.member_role_id' => 'required|exists:member_roles,id',
        ]);

        if (!$request->user()->hasRole('admin') && (int)$validated['section_id'] !== (int)$taskBoard->section_id) {
            abort(403, 'Only administrators can update the task board section.');
        }

        $taskBoard->update([
            'section_id' => $validated['section_id'],
        ]);

        $syncData = [];
        if (!empty($validated['members'])) {
            foreach ($validated['members'] as $m) {
                $syncData[$m['id']] = ['member_role_id' => $m['member_role_id']];
            }
        }
        $taskBoard->members()->sync($syncData);

        \App\Models\SystemLog::log('Update Task Board', "Task Board '{$taskBoard->name}' team and section assignments were updated.");

        return redirect()->back()->with('success', 'Task Board team updated successfully.');
    }

    public function destroy(TaskBoard $taskBoard): RedirectResponse
    {
        if (Gate::has('delete-task-board')) {
            Gate::authorize('delete-task-board');
        } else {
            Gate::authorize('delete-project');
        }

        $taskBoardName = $taskBoard->name;
        $taskBoard->delete();

        \App\Models\SystemLog::log('Delete Task Board', "Task Board '{$taskBoardName}' was deleted.");

        return redirect()->route('task-boards.index')->with('success', 'Task Board deleted successfully.');
    }
}
