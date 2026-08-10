<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\MemberRole;
use App\Models\Section;
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
        $response->assertRedirect(route('dashboard'));
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
            'member_role_ids' => $devRole ? [$devRole->id] : [],
        ]);

        $response->assertRedirect();
        
        $user = User::where('email', 'testdev@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test Developer', $user->name);
        $this->assertEquals($userRole->id, $user->role_id);
        
        $this->assertNotNull($user->member);
        if ($devRole) {
            $this->assertTrue($user->member->memberRoles->contains($devRole->id));
        }
    }

    /**
     * Admin can create a section and sync its members.
     */
    public function test_admin_can_create_section_and_assign_members(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) {
            $this->markTestSkipped('Seed data not available.');
        }

        // Get some members
        $members = Member::take(2)->pluck('id')->toArray();
        $department = \App\Models\Department::first() ?? \App\Models\Department::create(['name' => 'IT Department', 'short_name' => 'IT']);

        $response = $this->actingAs($admin)->post(route('admin.sections.store'), [
            'name' => 'Test Section Alpha',
            'department_id' => $department->id,
            'member_id' => $members[0] ?? null, // Project Manager
            'member_ids' => $members,
        ]);

        $response->assertRedirect();

        $section = Section::where('name', 'Test Section Alpha')->first();
        $this->assertNotNull($section);
        $this->assertEquals($members[0] ?? null, $section->member_id);
        
        if (!empty($members)) {
            $this->assertEquals(count($members), $section->members()->count());
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
     * Admin can update a functional role.
     */
    public function test_admin_can_update_functional_role(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $role = MemberRole::create([
            'name' => 'Test Temporary Role',
            'slug' => 'test-temporary-role',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.member-roles.update', $role->id), [
                'name' => 'Updated Temporary Role',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('member_roles', [
            'id' => $role->id,
            'name' => 'Updated Temporary Role',
            'slug' => 'updated-temporary-role',
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

    /**
     * Admin Staff can access the admin management dashboard.
     */
    public function test_admin_staff_can_access_admin_management(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        if (!$staff) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($staff)->get(route('admin.management'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Management'));
    }

    /**
     * Admin Staff can create a new user and assign them to a section they belong to.
     */
    public function test_admin_staff_can_create_user_and_assign_to_own_section(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        $userRole = Role::where('slug', 'user')->first();
        $devRole = MemberRole::where('slug', 'developer')->first();
        
        if (!$staff || !$userRole) {
            $this->markTestSkipped('Seed data not available.');
        }

        // Get the section Sarah Staff belongs to (Alpha Software Section)
        $section = $staff->member->sections->first();
        $this->assertNotNull($section);

        $response = $this->actingAs($staff)->post(route('admin.users.store'), [
            'name' => 'Sarah Junior',
            'username' => 'sarahjr',
            'email' => 'sarahjr@example.com',
            'password' => 'secret123',
            'role_id' => $userRole->id,
            'member_role_ids' => $devRole ? [$devRole->id] : [],
            'section_ids' => [$section->id],
        ]);

        $response->assertRedirect();

        $user = User::where('email', 'sarahjr@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Sarah Junior', $user->name);
        
        $this->assertNotNull($user->member);
        $this->assertTrue($user->member->sections->contains($section->id));
    }

    /**
     * Admin Staff cannot assign a user to a section they do not belong to.
     */
    public function test_admin_staff_cannot_assign_user_to_other_section(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        $userRole = Role::where('slug', 'user')->first();
        
        if (!$staff || !$userRole) {
            $this->markTestSkipped('Seed data not available.');
        }

        // Create another section Sarah Staff does not belong to
        $otherSection = Section::create(['name' => 'Other Section']);

        $response = $this->actingAs($staff)->post(route('admin.users.store'), [
            'name' => 'Sarah Blocked',
            'username' => 'sarahblocked',
            'email' => 'sarahblocked@example.com',
            'password' => 'secret123',
            'role_id' => $userRole->id,
            'member_role_ids' => [],
            'section_ids' => [$otherSection->id],
        ]);

        $response->assertSessionHasErrors('section_ids');
        $this->assertDatabaseMissing('users', ['email' => 'sarahblocked@example.com']);
    }

    /**
     * Admin Staff cannot delete users.
     */
    public function test_admin_staff_cannot_delete_users(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        $developer = User::where('email', 'developer@example.com')->first();
        
        if (!$staff || !$developer) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($staff)->delete(route('admin.users.destroy', $developer->id));
        $response->assertStatus(403);
        
        $this->assertDatabaseHas('users', ['id' => $developer->id]);
    }

    /**
     * Admin Staff cannot create a new user with System Role Administrator.
     */
    public function test_admin_staff_cannot_create_user_with_admin_role(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        $adminRole = Role::where('slug', 'admin')->first();

        if (!$staff || !$adminRole) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($staff)->post(route('admin.users.store'), [
            'name' => 'Fake Admin',
            'username' => 'fakeadmin',
            'email' => 'fakeadmin@example.com',
            'password' => 'secret123',
            'role_id' => $adminRole->id,
            'member_role_ids' => [],
            'section_ids' => [],
        ]);

        $response->assertSessionHasErrors('role_id');
        $this->assertDatabaseMissing('users', ['email' => 'fakeadmin@example.com']);
    }

    /**
     * Admin Staff cannot update details of users with System Role Administrator.
     */
    public function test_admin_staff_cannot_update_administrator_user(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        $adminUser = User::where('email', 'admin@example.com')->first();

        if (!$staff || !$adminUser) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($staff)->put(route('admin.users.update', $adminUser->id), [
            'name' => 'Attempted Edit Admin',
            'username' => $adminUser->username,
            'email' => $adminUser->email,
            'role_id' => $adminUser->role_id,
            'member_role_ids' => [],
            'section_ids' => [],
        ]);

        $response->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', [
            'id' => $adminUser->id,
            'name' => $adminUser->name,
        ]);
    }

    /**
     * Admin Staff cannot update details of users with Functional Role Department Head.
     */
    public function test_admin_staff_cannot_update_department_head_user(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        $deptHeadRole = MemberRole::where('slug', 'department_head')->first();
        
        // Find a user who is a Department Head
        $deptHeadUser = User::whereHas('member.memberRoles', function ($q) {
            $q->where('slug', 'department_head');
        })->first();

        if (!$staff || !$deptHeadUser) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($staff)->put(route('admin.users.update', $deptHeadUser->id), [
            'name' => 'Attempted Edit Dept Head',
            'username' => $deptHeadUser->username,
            'email' => $deptHeadUser->email,
            'role_id' => $deptHeadUser->role_id,
            'member_role_ids' => [$deptHeadRole->id],
            'section_ids' => [],
        ]);

        $response->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', [
            'id' => $deptHeadUser->id,
            'name' => $deptHeadUser->name,
        ]);
    }

    /**
     * Admin Staff can update details of regular users.
     */
    public function test_admin_staff_can_update_regular_user(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        $devUser = User::where('email', 'developer@example.com')->first();
        $userRole = Role::where('slug', 'user')->first();

        if (!$staff || !$devUser || !$userRole) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($staff)->put(route('admin.users.update', $devUser->id), [
            'name' => 'Updated Developer Name',
            'username' => $devUser->username,
            'email' => $devUser->email,
            'role_id' => $userRole->id,
            'member_role_ids' => [],
            'section_ids' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $devUser->id,
            'name' => 'Updated Developer Name',
        ]);
    }
}
