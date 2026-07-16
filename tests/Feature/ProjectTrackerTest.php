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
            ->has('phases')
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
     * Admin can create a project with phases.
     */
    public function test_admin_can_create_project_with_phases(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);

        $section = \App\Models\Section::first();
        $this->assertNotNull($section);

        $phases = DevelopmentPhase::take(3)->pluck('id')->toArray();
        $this->assertNotEmpty($phases);

        $response = $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'New Awesome Project',
            'description' => 'A description',
            'status' => 'planning',
            'section_id' => $section->id,
            'start_date' => '2026-07-16',
            'end_date' => '2026-08-16',
            'phase_ids' => $phases,
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('projects', [
            'name' => 'New Awesome Project',
            'section_id' => $section->id,
        ]);

        $project = Project::where('name', 'New Awesome Project')->first();
        $this->assertNotNull($project);
        $this->assertEquals(count($phases), $project->developmentPhases()->count());
    }
}

