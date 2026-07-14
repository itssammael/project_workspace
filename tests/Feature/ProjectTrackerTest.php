<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\DevelopmentPhase;
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
     * Test team member can view their project details.
     */
    public function test_team_member_can_view_project_details(): void
    {
        $designer = User::where('email', 'designer@example.com')->first();
        $project = Project::first();
        $this->assertNotNull($project);

        $response = $this->actingAs($designer)->get(route('projects.show', $project->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Project/Show')
            ->has('project')
            ->has('phases')
        );
    }

    /**
     * Test non-team member cannot view project details.
     */
    public function test_non_team_member_cannot_view_project_details(): void
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
     * Guest/Non-admin cannot update project team.
     */
    public function test_non_admin_cannot_update_project_team(): void
    {
        $project = Project::first();
        $this->assertNotNull($project);

        // Guest
        $response = $this->put(route('projects.update', $project->id), [
            'team_id' => 1,
        ]);
        $response->assertRedirect('/login');

        // Non-admin (PM)
        $pm = User::where('email', 'manager@example.com')->first();
        $response = $this->actingAs($pm)->put(route('projects.update', $project->id), [
            'team_id' => 1,
        ]);
        $response->assertStatus(403);
    }

    /**
     * Admin can update project team.
     */
    public function test_admin_can_update_project_team(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $project = Project::first();
        $this->assertNotNull($project);

        // Create a new team to assign
        $otherTeam = \App\Models\Team::create([
            'name' => 'Beta Software Team',
            'member_id' => $project->team->member_id,
        ]);

        $response = $this->actingAs($admin)->put(route('projects.update', $project->id), [
            'team_id' => $otherTeam->id,
        ]);
        $response->assertRedirect();

        $this->assertEquals($otherTeam->id, $project->fresh()->team_id);
    }
}

