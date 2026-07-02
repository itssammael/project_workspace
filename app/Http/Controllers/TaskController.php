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
            'deliverables' => 'nullable|string',
            'duration' => 'required|integer|min:1',
            'development_phase_id' => 'required|exists:development_phases,id',
            'member_id' => 'nullable|exists:members,id',
            'start_date' => 'required|date',
            'status' => 'required|string|in:pending,in_progress,completed',
        ]);

        $project->tasks()->create($validated);

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
                'deliverables' => 'nullable|string',
                'duration' => 'required|integer|min:1',
                'development_phase_id' => 'required|exists:development_phases,id',
                'member_id' => 'nullable|exists:members,id',
                'start_date' => 'required|date',
                'status' => 'required|string|in:pending,in_progress,completed',
            ]);
            $task->update($validated);
        } else if ($member && $task->member_id === $member->id) {
            // Assigned member: can only update status
            $validated = $request->validate([
                'status' => 'required|string|in:pending,in_progress,completed',
            ]);
            $task->update($validated);
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
}
