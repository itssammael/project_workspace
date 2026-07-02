<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\DevelopmentPhase;
use App\Models\Task;
use Tests\TestCase;

class ProjectTrackerTest extends TestCase
{
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
        $response->assertSee('Project Workspace');
        $response->assertSee('Alex Administrator');
    }

    /**
     * Test RBAC for project creation: Admin/Dept Head can create, PM cannot.
     */
    public function test_rbac_project_creation_authorization(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $pm = User::where('email', 'manager@example.com')->first();

        // Admin (Department Head role) can view create page
        $response = $this->actingAs($admin)->get('/projects/create');
        $response->assertStatus(200);

        // PM cannot view create page
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

        // Find a task assigned to the designer
        $task = Task::where('member_id', $designer->member->id)->first();
        $this->assertNotNull($task);

        // Designer updates status to in_progress
        $response = $this->actingAs($designer)->put(route('tasks.update', $task->id), [
            'status' => 'in_progress',
        ]);

        $response->assertRedirect();
        $this->assertEquals('in_progress', $task->fresh()->status);
    }

    /**
     * Test task updates: other members cannot update status.
     */
    public function test_unauthorized_member_cannot_update_task(): void
    {
        $developer = User::where('email', 'developer@example.com')->first();
        $designer = User::where('email', 'designer@example.com')->first();

        // Find a task assigned to the designer
        $task = Task::where('member_id', $designer->member->id)->first();

        // Developer tries to update designer's task status
        $response = $this->actingAs($developer)->put(route('tasks.update', $task->id), [
            'status' => 'completed',
        ]);

        $response->assertStatus(403);
    }
}
