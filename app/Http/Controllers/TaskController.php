<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\RedirectResponse;

class TaskController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-tasks', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'details' => 'nullable|string',
            'workflow_id' => 'required|exists:workflows,id',
            // Option A fields (nullable/optional fallback)
            'deliverables' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'member_id' => 'nullable|exists:members,id',
            'start_date' => 'nullable|date',
            'status' => 'nullable|string|in:pending,in_progress,submitted,completed',
            // Option B fields (subtasks array)
            'subtasks' => 'nullable|array',
            'subtasks.*.name' => 'required_with:subtasks|string|max:255',
            'subtasks.*.details' => 'nullable|string',
            'subtasks.*.deliverables' => 'nullable|string',
            'subtasks.*.duration' => 'required_with:subtasks|integer|min:1',
            'subtasks.*.member_id' => 'nullable|exists:members,id',
            'subtasks.*.start_date' => 'required_with:subtasks|date',
            'subtasks.*.status' => 'required_with:subtasks|string|in:pending,in_progress,submitted,completed',
        ]);

        $task = $project->tasks()->create([
            'name' => $validated['name'],
            'details' => $validated['details'],
            'workflow_id' => $validated['workflow_id'],
        ]);

        if (!empty($validated['subtasks'])) {
            foreach ($validated['subtasks'] as $subtaskData) {
                $task->subTasks()->create([
                    'name' => $subtaskData['name'],
                    'details' => $subtaskData['details'] ?? null,
                    'deliverables' => $subtaskData['deliverables'] ?? null,
                    'duration' => $subtaskData['duration'],
                    'member_id' => $subtaskData['member_id'] ?? null,
                    'start_date' => $subtaskData['start_date'],
                    'status' => $subtaskData['status'] ?? 'pending',
                ]);
            }
        } else {
            $task->subTasks()->create([
                'name' => $validated['name'] . ' Subtask',
                'details' => $validated['details'],
                'deliverables' => $validated['deliverables'] ?? null,
                'duration' => $validated['duration'] ?? 1,
                'member_id' => $validated['member_id'] ?? null,
                'start_date' => $validated['start_date'] ?? now(),
                'status' => $validated['status'] ?? 'pending',
            ]);
        }

        \App\Models\SystemLog::log('Create Task', "Task '{$task->name}' was created in project '{$project->name}'.");

        return redirect()->back()->with('success', 'Task created successfully.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        $member = $user->member;
        $project = $task->project;

        // Check if the user is a PM for the project or admin
        $isPM = Gate::allows('manage-tasks', $project);

        if ($isPM) {
            // Full update
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'details' => 'nullable|string',
                'workflow_id' => 'required|exists:workflows,id',
                // Option A fields (nullable/optional fallback)
                'deliverables' => 'nullable|string',
                'duration' => 'nullable|integer|min:1',
                'member_id' => 'nullable|exists:members,id',
                'start_date' => 'nullable|date',
                'status' => 'nullable|string|in:pending,in_progress,submitted,completed',
                // Option B fields (subtasks array)
                'subtasks' => 'nullable|array',
                'subtasks.*.id' => 'nullable|exists:sub_tasks,id',
                'subtasks.*.name' => 'required_with:subtasks|string|max:255',
                'subtasks.*.details' => 'nullable|string',
                'subtasks.*.deliverables' => 'nullable|string',
                'subtasks.*.duration' => 'required_with:subtasks|integer|min:1',
                'subtasks.*.member_id' => 'nullable|exists:members,id',
                'subtasks.*.start_date' => 'required_with:subtasks|date',
                'subtasks.*.status' => 'required_with:subtasks|string|in:pending,in_progress,submitted,completed',
            ]);

            $task->update([
                'name' => $validated['name'],
                'details' => $validated['details'],
                'workflow_id' => $validated['workflow_id'],
            ]);

            if (isset($validated['subtasks'])) {
                $submittedIds = collect($validated['subtasks'])->pluck('id')->filter()->toArray();
                $task->subTasks()->whereNotIn('id', $submittedIds)->delete();

                foreach ($validated['subtasks'] as $subtaskData) {
                    if (!empty($subtaskData['id'])) {
                        $subTask = $task->subTasks()->find($subtaskData['id']);
                        if ($subTask) {
                            $subTask->update([
                                'name' => $subtaskData['name'],
                                'details' => $subtaskData['details'] ?? null,
                                'deliverables' => $subtaskData['deliverables'] ?? null,
                                'duration' => $subtaskData['duration'],
                                'member_id' => $subtaskData['member_id'] ?? null,
                                'start_date' => $subtaskData['start_date'],
                                'status' => $subtaskData['status'],
                            ]);
                        }
                    } else {
                        $task->subTasks()->create([
                            'name' => $subtaskData['name'],
                            'details' => $subtaskData['details'] ?? null,
                            'deliverables' => $subtaskData['deliverables'] ?? null,
                            'duration' => $subtaskData['duration'],
                            'member_id' => $subtaskData['member_id'] ?? null,
                            'start_date' => $subtaskData['start_date'],
                            'status' => $subtaskData['status'] ?? 'pending',
                        ]);
                    }
                }
            } else {
                $subTask = $task->subTasks()->first();
                if ($subTask) {
                    $subTask->update([
                        'name' => $validated['name'] . ' Subtask',
                        'details' => $validated['details'],
                        'deliverables' => $validated['deliverables'] ?? null,
                        'duration' => $validated['duration'] ?? 1,
                        'member_id' => $validated['member_id'] ?? null,
                        'start_date' => $validated['start_date'] ?? now(),
                        'status' => $validated['status'] ?? 'pending',
                    ]);
                } else {
                    $task->subTasks()->create([
                        'name' => $validated['name'] . ' Subtask',
                        'details' => $validated['details'],
                        'deliverables' => $validated['deliverables'] ?? null,
                        'duration' => $validated['duration'] ?? 1,
                        'member_id' => $validated['member_id'] ?? null,
                        'start_date' => $validated['start_date'] ?? now(),
                        'status' => $validated['status'] ?? 'pending',
                    ]);
                }
            }
            \App\Models\SystemLog::log('Update Task', "Task '{$task->name}' and its subtasks were updated.");
        } else if ($member && $subTask = $task->subTasks()->where('member_id', $member->id)->first()) {
            // Assigned member: can only update status
            $validated = $request->validate([
                'status' => 'required|string|in:pending,in_progress,submitted,completed',
            ]);
            $subTask->update(['status' => $validated['status']]);
            \App\Models\SystemLog::log('Update Subtask Status', "Subtask '{$subTask->name}' (under task '{$task->name}') status updated to '{$validated['status']}'.");
        } else {
            abort(403, 'Unauthorized action.');
        }

        return redirect()->back()->with('success', 'Task updated successfully.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('manage-tasks', $task->project);
        $taskName = $task->name;
        $task->delete();
        \App\Models\SystemLog::log('Delete Task', "Task '{$taskName}' was deleted.");
        return redirect()->back()->with('success', 'Task deleted successfully.');
    }

    /**
     * Update the status of a specific subtask.
     */
    public function updateSubtaskStatus(Request $request, \App\Models\SubTask $subTask): RedirectResponse
    {
        $user = $request->user();
        $member = $user->member;

        // Either Project Manager (Gate) or the assigned member can update status
        $isPM = Gate::allows('manage-tasks', $subTask->task->project);
        $isAssignee = $member && $subTask->member_id === $member->id;

        if (!$isPM && !$isAssignee) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,in_progress,submitted,completed',
        ]);

        $subTask->update([
            'status' => $validated['status'],
        ]);

        \App\Models\SystemLog::log('Update Subtask Status', "Subtask '{$subTask->name}' status updated to '{$validated['status']}'.");

        return redirect()->back()->with('success', 'Subtask status updated successfully.');
    }

    /**
     * Move a Kanban task card to a new stage and update its status.
     */
    public function moveKanbanCard(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        $member = $user->member;
        $project = $task->project;

        // Check if the user is a PM for the project or admin
        $isPM = Gate::allows('manage-tasks', $project) || ($user && $user->role_id === 1); // admin role_id is 1 or check hasRole('admin')
        $isAssignee = $member && $task->subTasks()->where('member_id', $member->id)->exists();

        if (!$isPM && !$isAssignee) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'workflow_id' => 'required|exists:workflows,id',
        ]);

        $targetWorkflow = \App\Models\Workflow::findOrFail($validated['workflow_id']);

        $targetName = strtolower(trim($targetWorkflow->name));

        // Get the task's current status based on its subtasks
        $subTasks = $task->subTasks;
        if ($subTasks->isEmpty()) {
            $currentStatus = 'pending';
        } else if ($subTasks->every(fn($st) => $st->status === 'completed')) {
            $currentStatus = 'completed';
        } else if ($subTasks->every(fn($st) => $st->status === 'pending')) {
            $currentStatus = 'pending';
        } else if ($subTasks->every(fn($st) => $st->status === 'completed' || $st->status === 'submitted')) {
            $currentStatus = 'submitted';
        } else {
            $currentStatus = 'in_progress';
        }

        // 1. Move FROM Completed: Only PM/Admin can move, and only to Submitted stage
        if ($currentStatus === 'completed') {
            if (!$isPM) {
                abort(403, 'Only Project Managers can move completed tasks.');
            }
            if ($targetName !== 'submitted') {
                abort(403, 'Completed tasks can only be moved to the Submitted stage.');
            }
        }

        // 2. Move TO Completed: Only PM/Admin, and only if currently in Submitted stage
        if ($targetName === 'completed' || $targetName === 'done') {
            if (!$isPM) {
                abort(403, 'Only Project Managers can move tasks to Completed.');
            }
            if ($currentStatus !== 'submitted') {
                abort(403, 'Tasks can only be moved to Completed from the Submitted stage.');
            }
        }

        // 2. Map target workflow name to subtask status
        $statusMap = [
            'to do' => 'pending',
            'to-do' => 'pending',
            'todo' => 'pending',
            'doing' => 'in_progress',
            'in progress' => 'in_progress',
            'in-progress' => 'in_progress',
            'submitted' => 'submitted',
            'completed' => 'completed',
            'done' => 'completed',
        ];

        $newStatus = $statusMap[$targetName] ?? 'pending';

        // Update all subtasks to the new status
        $task->subTasks()->update([
            'status' => $newStatus,
        ]);

        \App\Models\SystemLog::log('Move Task Card', "Moved task '{$task->name}' to stage '{$targetWorkflow->name}' (status set to '{$newStatus}').");

        return redirect()->back()->with('success', 'Task moved successfully.');
    }

    /**
     * Store a new subtask attachment.
     */
    public function storeAttachment(\Illuminate\Http\Request $request, \App\Models\SubTask $subTask): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $member = $user->member;
        
        if (!$member || $subTask->member_id !== $member->id) {
            abort(403, 'Only the assignee can attach deliverables.');
        }

        $validated = $request->validate([
            'attachment_type' => 'required|string|in:File Upload,Commit ID,Ticket Link',
            'attachment' => 'required',
        ]);

        $attachmentValue = '';

        if ($validated['attachment_type'] === 'File Upload') {
            $request->validate([
                'attachment' => 'required|file',
            ]);
            $file = $request->file('attachment');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Ensure the attachments folder exists
            if (!file_exists(public_path('attachments'))) {
                mkdir(public_path('attachments'), 0755, true);
            }
            
            $file->move(public_path('attachments'), $filename);
            $attachmentValue = $filename;
        } else {
            $request->validate([
                'attachment' => 'required|string',
            ]);
            $attachmentValue = $request->input('attachment');
        }

        \App\Models\SubTaskAttachment::create([
            'sub_task_id' => $subTask->id,
            'attachment_type' => $validated['attachment_type'],
            'attachment' => $attachmentValue,
        ]);

        \App\Models\SystemLog::log('Add Attachment', "Added attachment of type '{$validated['attachment_type']}' to subtask '{$subTask->name}'.");

        return redirect()->back()->with('success', 'Attachment submitted successfully.');
    }

    /**
     * Store a new subtask comment.
     */
    public function storeComment(\Illuminate\Http\Request $request, \App\Models\SubTask $subTask): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            abort(403, 'Unauthorized.');
        }

        $project = $subTask->task->project;

        $isAssignee = $subTask->member_id === $member->id;
        
        $isPM = $member->memberRoles()->where('slug', 'project_manager')->exists() && 
                $project->section && 
                $project->section->member_id === $member->id;

        $isDeptHead = $member->memberRoles()->where('slug', 'department_head')->exists();
        
        $isAdmin = $user->role_id === 1;

        if (!$isAssignee && !$isPM && !$isDeptHead && !$isAdmin) {
            abort(403, 'Unauthorized to view or comment on this subtask.');
        }

        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        \App\Models\SubTaskComment::create([
            'sub_task_id' => $subTask->id,
            'member_id' => $member->id,
            'comment' => $validated['comment'],
        ]);

        \App\Models\SystemLog::log('Add Comment', "Added comment to subtask '{$subTask->name}'.");

        return redirect()->back()->with('success', 'Comment added successfully.');
    }
}
