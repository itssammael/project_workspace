<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Workflow;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;
    /**
     * Test guest redirection.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    /**
     * Test admin dashboard access.
     */
    public function test_admin_can_access_dashboard_and_see_projects(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')->has('projects'));
        $response->assertSee('Alex Administrator');
    }

    /**
     * Test admin can access project creation page.
     */
    public function test_admin_can_access_project_creation(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $response = $this->actingAs($admin)->get('/projects/create');
        $response->assertStatus(200);
    }

    /**
     * Test PM cannot access project creation page.
     */
    public function test_pm_cannot_access_project_creation(): void
    {
        $pm = User::where('email', 'manager@example.com')->first();
        $response = $this->actingAs($pm)->get('/projects/create');
        $response->assertStatus(403);
    }

    /**
     * Test task updates: assignee can update status.
     */
    public function test_assignee_can_update_task_status(): void
    {
        $designer = User::where('email', 'designer@example.com')->first();
        $this->assertNotNull($designer);

        // Find a task assigned to the designer via its subtask
        $subTask = \App\Models\SubTask::where('member_id', $designer->member->id)->first();
        $this->assertNotNull($subTask);
        $task = $subTask->task;
        $this->assertNotNull($task);

        // Designer updates status to in_progress
        $response = $this->actingAs($designer)->put(route('tasks.update', $task->id), [
            'status' => 'in_progress',
        ]);

        $response->assertRedirect();
        $this->assertEquals('in_progress', $subTask->fresh()->status);
    }

    /**
     * Test task updates: assignee can update status to submitted.
     */
    public function test_assignee_can_update_task_status_to_submitted(): void
    {
        $designer = User::where('email', 'designer@example.com')->first();
        $this->assertNotNull($designer);

        // Find a task assigned to the designer via its subtask
        $subTask = \App\Models\SubTask::where('member_id', $designer->member->id)->first();
        $this->assertNotNull($subTask);
        $task = $subTask->task;
        $this->assertNotNull($task);

        // Designer updates status to submitted
        $response = $this->actingAs($designer)->put(route('tasks.update', $task->id), [
            'status' => 'submitted',
        ]);

        $response->assertRedirect();
        $this->assertEquals('submitted', $subTask->fresh()->status);
    }

    /**
     * Test that an assignee can move a task across non-completed stages.
     */
    public function test_assignee_can_move_kanban_card_to_non_completed_stages(): void
    {
        $designer = User::where('email', 'designer@example.com')->first();
        $task = Task::first();
        
        $kanbanType = \App\Models\WorkflowType::where('name', 'Kanban')->first();
        $doingStage = Workflow::where('name', 'Doing')->where('workflow_type_id', $kanbanType->id)->first();

        $initialWorkflowId = $task->workflow_id;
        $subtask = $task->subTasks()->first();
        $subtask->update(['member_id' => $designer->member->id, 'status' => 'pending']);

        $response = $this->actingAs($designer)->put(route('tasks.move', $task->id), [
            'workflow_id' => $doingStage->id,
        ]);
        
        $response->assertRedirect();
        $this->assertEquals($initialWorkflowId, $task->fresh()->workflow_id);
        $this->assertEquals('in_progress', $subtask->fresh()->status);
    }

    /**
     * Test that a designer (non-PM) cannot move a card to the completed stage.
     */
    public function test_designer_cannot_move_kanban_card_to_completed(): void
    {
        $designer = User::where('email', 'designer@example.com')->first();
        $task = Task::first();
        
        $kanbanType = \App\Models\WorkflowType::where('name', 'Kanban')->first();
        $completedStage = Workflow::where('name', 'Completed')->where('workflow_type_id', $kanbanType->id)->first();

        $subtask = $task->subTasks()->first();
        $subtask->update(['member_id' => $designer->member->id, 'status' => 'submitted']);

        $response = $this->actingAs($designer)->put(route('tasks.move', $task->id), [
            'workflow_id' => $completedStage->id,
        ]);
        
        $response->assertStatus(403);
    }

    /**
     * Test that PM cannot move a task directly to completed unless it is in the submitted stage.
     */
    public function test_pm_cannot_move_kanban_card_to_completed_unless_submitted(): void
    {
        $pm = User::where('email', 'manager@example.com')->first();
        $task = Task::first();
        
        $kanbanType = \App\Models\WorkflowType::where('name', 'Kanban')->first();
        $completedStage = Workflow::where('name', 'Completed')->where('workflow_type_id', $kanbanType->id)->first();

        $task->subTasks()->update(['status' => 'in_progress']);

        $response = $this->actingAs($pm)->put(route('tasks.move', $task->id), [
            'workflow_id' => $completedStage->id,
        ]);
        
        $response->assertStatus(403);
    }

    /**
     * Test that PM can move a task from submitted to completed.
     */
    public function test_pm_can_move_submitted_kanban_card_to_completed(): void
    {
        $pm = User::where('email', 'manager@example.com')->first();
        $task = Task::first();
        
        $kanbanType = \App\Models\WorkflowType::where('name', 'Kanban')->first();
        $completedStage = Workflow::where('name', 'Completed')->where('workflow_type_id', $kanbanType->id)->first();

        $initialWorkflowId = $task->workflow_id;
        $subtask = $task->subTasks()->first();
        $subtask->update(['status' => 'submitted']);

        $response = $this->actingAs($pm)->put(route('tasks.move', $task->id), [
            'workflow_id' => $completedStage->id,
        ]);
        
        $response->assertRedirect();
        $this->assertEquals($initialWorkflowId, $task->fresh()->workflow_id);
        $this->assertEquals('completed', $subtask->fresh()->status);
    }

    /**
     * Test that a designer (non-PM) cannot move a completed card.
     */
    public function test_designer_cannot_move_completed_kanban_card(): void
    {
        $designer = User::where('email', 'designer@example.com')->first();
        $task = Task::first();
        
        $kanbanType = \App\Models\WorkflowType::where('name', 'Kanban')->first();
        $todoStage = Workflow::where('name', 'To do')->where('workflow_type_id', $kanbanType->id)->first();

        $subtask = $task->subTasks()->first();
        $subtask->update(['member_id' => $designer->member->id, 'status' => 'completed']);

        $response = $this->actingAs($designer)->put(route('tasks.move', $task->id), [
            'workflow_id' => $todoStage->id,
        ]);
        
        $response->assertStatus(403);
    }

    /**
     * Test that PM cannot move a completed task to doing stage.
     */
    public function test_pm_cannot_move_completed_kanban_card_to_doing(): void
    {
        $pm = User::where('email', 'manager@example.com')->first();
        $task = Task::first();
        
        $kanbanType = \App\Models\WorkflowType::where('name', 'Kanban')->first();
        $doingStage = Workflow::where('name', 'Doing')->where('workflow_type_id', $kanbanType->id)->first();

        $subtask = $task->subTasks()->first();
        $subtask->update(['status' => 'completed']);

        $response = $this->actingAs($pm)->put(route('tasks.move', $task->id), [
            'workflow_id' => $doingStage->id,
        ]);
        
        $response->assertStatus(403);
    }

    /**
     * Test that PM can move a completed task to Submitted stage.
     */
    public function test_pm_can_move_completed_kanban_card_to_submitted(): void
    {
        $pm = User::where('email', 'manager@example.com')->first();
        $task = Task::first();
        
        $kanbanType = \App\Models\WorkflowType::where('name', 'Kanban')->first();
        $submittedStage = Workflow::where('name', 'Submitted')->where('workflow_type_id', $kanbanType->id)->first();

        $initialWorkflowId = $task->workflow_id;
        $subtask = $task->subTasks()->first();
        $subtask->update(['status' => 'completed']);

        $response = $this->actingAs($pm)->put(route('tasks.move', $task->id), [
            'workflow_id' => $submittedStage->id,
        ]);
        
        $response->assertRedirect();
        $this->assertEquals($initialWorkflowId, $task->fresh()->workflow_id);
        $this->assertEquals('submitted', $subtask->fresh()->status);
    }

    /**
     * Test task updates: other members cannot update status.
     */
    public function test_unauthorized_member_cannot_update_task(): void
    {
        $developer = User::where('email', 'developer@example.com')->first();
        $designer = User::where('email', 'designer@example.com')->first();

        // Find a task assigned to the designer via its subtask
        $subTask = \App\Models\SubTask::where('member_id', $designer->member->id)->first();
        $this->assertNotNull($subTask);
        $task = $subTask->task;

        // Developer tries to update designer's task status
        $response = $this->actingAs($developer)->put(route('tasks.update', $task->id), [
            'status' => 'completed',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test admin can view any project details.
     */
    public function test_admin_can_view_project_details(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $project = Project::first();
        $this->assertNotNull($project);

        $response = $this->actingAs($admin)->get(route('projects.show', $project->id));
        $response->assertStatus(200);
    }

    /**
     * Test section member can view their project details.
     */
    public function test_section_member_can_view_project_details(): void
    {
        $designer = User::where('email', 'designer@example.com')->first();
        $project = Project::first();
        $this->assertNotNull($project);

        $response = $this->actingAs($designer)->get(route('projects.show', $project->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Project/Show')
            ->has('project')
            ->has('workflows')
        );
    }

    /**
     * Test non-section member cannot view project details.
     */
    public function test_non_section_member_cannot_view_project_details(): void
    {
        $viewer = User::where('email', 'viewer@example.com')->first();
        $project = Project::first();
        $this->assertNotNull($project);

        $response = $this->actingAs($viewer)->get(route('projects.show', $project->id));
        $response->assertStatus(403);
    }

    /**
     * Test user can log in using their email or username.
     */
    public function test_user_can_login_using_email_or_username(): void
    {
        // 1. Login with email
        $response = $this->post('/login', [
            'email' => 'manager@example.com',
            'password' => 'password',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        // Logout
        $this->post('/logout');
        $this->assertGuest();

        // 2. Login with username
        $response = $this->post('/login', [
            'email' => 'manager',
            'password' => 'password',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    /**
     * Guest/Non-admin cannot delete a project.
     */
    public function test_non_admin_cannot_delete_project(): void
    {
        $project = Project::first();
        $this->assertNotNull($project);

        // Guest
        $response = $this->delete(route('projects.destroy', $project->id));
        $response->assertRedirect('/login');

        // Non-admin (PM)
        $pm = User::where('email', 'manager@example.com')->first();
        $response = $this->actingAs($pm)->delete(route('projects.destroy', $project->id));
        $response->assertStatus(403);
    }

    /**
     * Admin can delete a project.
     */
    public function test_admin_can_delete_project(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $project = Project::first();
        $this->assertNotNull($project);

        $response = $this->actingAs($admin)->delete(route('projects.destroy', $project->id));
        $response->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
        ]);
    }

    /**
     * Guest/Non-admin cannot update project section.
     */
    public function test_non_admin_cannot_update_project_section(): void
    {
        $project = Project::first();
        $this->assertNotNull($project);

        // Guest
        $response = $this->put(route('projects.update', $project->id), [
            'section_id' => 1,
        ]);
        $response->assertRedirect('/login');

        // Non-admin (PM)
        $pm = User::where('email', 'manager@example.com')->first();
        $response = $this->actingAs($pm)->put(route('projects.update', $project->id), [
            'section_id' => 1,
        ]);
        $response->assertStatus(403);
    }

    /**
     * Admin can update project section.
     */
    public function test_admin_can_update_project_section(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $project = Project::first();
        $this->assertNotNull($project);

        // Create a new section to assign
        $otherSection = \App\Models\Section::create([
            'name' => 'Beta Software Section',
            'member_id' => $project->section->member_id,
        ]);

        $response = $this->actingAs($admin)->put(route('projects.update', $project->id), [
            'section_id' => $otherSection->id,
        ]);
        $response->assertRedirect();

        $this->assertEquals($otherSection->id, $project->fresh()->section_id);
    }

    /**
     * Admin can create a project with workflows.
     */
    public function test_admin_can_create_project_with_workflows(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);

        $section = \App\Models\Section::first();
        $this->assertNotNull($section);

        $workflows = Workflow::take(3)->pluck('id')->toArray();
        $this->assertNotEmpty($workflows);

        $response = $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'New Awesome Project',
            'description' => 'A description',
            'status' => 'planning',
            'section_id' => $section->id,
            'start_date' => '2026-07-16',
            'end_date' => '2026-08-16',
            'workflow_ids' => $workflows,
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('projects', [
            'name' => 'New Awesome Project',
            'section_id' => $section->id,
        ]);

        $project = Project::where('name', 'New Awesome Project')->first();
        $this->assertNotNull($project);
        $this->assertEquals(count($workflows), $project->workflows()->count());
    }
}

