<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\DevelopmentPhase;
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
            'development_phase_id' => 'required|exists:development_phases,id',
            // Option A fields (nullable/optional fallback)
            'deliverables' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'member_id' => 'nullable|exists:members,id',
            'start_date' => 'nullable|date',
            'status' => 'nullable|string|in:pending,in_progress,completed',
            // Option B fields (subtasks array)
            'subtasks' => 'nullable|array',
            'subtasks.*.name' => 'required_with:subtasks|string|max:255',
            'subtasks.*.details' => 'nullable|string',
            'subtasks.*.deliverables' => 'nullable|string',
            'subtasks.*.duration' => 'required_with:subtasks|integer|min:1',
            'subtasks.*.member_id' => 'nullable|exists:members,id',
            'subtasks.*.start_date' => 'required_with:subtasks|date',
            'subtasks.*.status' => 'required_with:subtasks|string|in:pending,in_progress,completed',
        ]);

        $task = $project->tasks()->create([
            'name' => $validated['name'],
            'details' => $validated['details'],
            'development_phase_id' => $validated['development_phase_id'],
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
                'development_phase_id' => 'required|exists:development_phases,id',
                // Option A fields (nullable/optional fallback)
                'deliverables' => 'nullable|string',
                'duration' => 'nullable|integer|min:1',
                'member_id' => 'nullable|exists:members,id',
                'start_date' => 'nullable|date',
                'status' => 'nullable|string|in:pending,in_progress,completed',
                // Option B fields (subtasks array)
                'subtasks' => 'nullable|array',
                'subtasks.*.id' => 'nullable|exists:sub_tasks,id',
                'subtasks.*.name' => 'required_with:subtasks|string|max:255',
                'subtasks.*.details' => 'nullable|string',
                'subtasks.*.deliverables' => 'nullable|string',
                'subtasks.*.duration' => 'required_with:subtasks|integer|min:1',
                'subtasks.*.member_id' => 'nullable|exists:members,id',
                'subtasks.*.start_date' => 'required_with:subtasks|date',
                'subtasks.*.status' => 'required_with:subtasks|string|in:pending,in_progress,completed',
            ]);

            $task->update([
                'name' => $validated['name'],
                'details' => $validated['details'],
                'development_phase_id' => $validated['development_phase_id'],
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
        } else if ($member && $subTask = $task->subTasks()->where('member_id', $member->id)->first()) {
            // Assigned member: can only update status
            $validated = $request->validate([
                'status' => 'required|string|in:pending,in_progress,completed',
            ]);
            $subTask->update(['status' => $validated['status']]);
        } else {
            abort(403, 'Unauthorized action.');
        }

        return redirect()->back()->with('success', 'Task updated successfully.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('manage-tasks', $task->project);
        $task->delete();
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
            'status' => 'required|string|in:pending,in_progress,completed',
        ]);

        $subTask->update([
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', 'Subtask status updated successfully.');
    }
}
