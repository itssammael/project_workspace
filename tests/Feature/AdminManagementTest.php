<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\MemberRole;
use App\Models\Team;
use App\Models\Member;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;
    /**
     * Guest cannot access admin management.
     */
    public function test_guest_cannot_access_admin_management(): void
    {
        $response = $this->get(route('admin.management'));
        $response->assertRedirect('/login');
    }

    /**
     * Non-admin users cannot access admin management.
     */
    public function test_non_admin_cannot_access_admin_management(): void
    {
        $user = User::where('email', 'manager@example.com')->first();
        if (!$user) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($user)->get(route('admin.management'));
        $response->assertStatus(403);
    }

    /**
     * Administrator can access admin management dashboard.
     */
    public function test_admin_can_access_admin_management(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($admin)->get(route('admin.management'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Management'));
    }

    /**
     * Admin can create a new user and member.
     */
    public function test_admin_can_create_user_and_member(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $userRole = Role::where('slug', 'user')->first();
        $devRole = MemberRole::where('slug', 'developer')->first();

        if (!$admin || !$userRole) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Test Developer',
            'username' => 'testdev',
            'email' => 'testdev@example.com',
            'password' => 'secret123',
            'role_id' => $userRole->id,
            'member_role_id' => $devRole ? $devRole->id : null,
        ]);

        $response->assertRedirect();
        
        $user = User::where('email', 'testdev@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test Developer', $user->name);
        $this->assertEquals($userRole->id, $user->role_id);
        
        $this->assertNotNull($user->member);
        if ($devRole) {
            $this->assertEquals($devRole->id, $user->member->member_role_id);
        }
    }

    /**
     * Admin can create a team and sync its members.
     */
    public function test_admin_can_create_team_and_assign_members(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) {
            $this->markTestSkipped('Seed data not available.');
        }

        // Get some members
        $members = Member::take(2)->pluck('id')->toArray();

        $response = $this->actingAs($admin)->post(route('admin.teams.store'), [
            'name' => 'Test Team Alpha',
            'member_id' => $members[0] ?? null, // Project Manager
            'member_ids' => $members,
        ]);

        $response->assertRedirect();

        $team = Team::where('name', 'Test Team Alpha')->first();
        $this->assertNotNull($team);
        $this->assertEquals($members[0] ?? null, $team->member_id);
        
        if (!empty($members)) {
            $this->assertEquals(count($members), $team->members()->count());
        }
    }

    /**
     * Admin can create a new functional role.
     */
    public function test_admin_can_create_functional_role(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin)
            ->post(route('admin.member-roles.store'), [
                'name' => 'QA Architect',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('member_roles', [
            'name' => 'QA Architect',
            'slug' => 'qa-architect',
        ]);
    }

    /**
     * Admin can delete a functional role.
     */
    public function test_admin_can_delete_functional_role(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $role = MemberRole::create([
            'name' => 'Test Temporary Role',
            'slug' => 'test-temporary-role',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.member-roles.destroy', $role->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('member_roles', [
            'id' => $role->id,
        ]);
    }

    /**
     * Admin can bulk delete users.
     */
    public function test_admin_can_bulk_delete_users(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        
        $user1 = User::factory()->create(['email' => 't1@example.com', 'username' => 't1']);
        $user2 = User::factory()->create(['email' => 't2@example.com', 'username' => 't2']);

        // Create associated members
        Member::create(['user_id' => $user1->id]);
        Member::create(['user_id' => $user2->id]);

        $this->actingAs($admin)
            ->post(route('admin.users.bulk-destroy'), [
                'ids' => [$user1->id, $user2->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'id' => $user1->id,
        ]);
        $this->assertDatabaseMissing('users', [
            'id' => $user2->id,
        ]);
        $this->assertDatabaseMissing('members', [
            'user_id' => $user1->id,
        ]);
        $this->assertDatabaseMissing('members', [
            'user_id' => $user2->id,
        ]);
    }
}
