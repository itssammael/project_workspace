<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use App\Models\SystemRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /**
     * Guest cannot access admin settings.
     */
    public function test_guest_cannot_access_admin_settings(): void
    {
        $response = $this->get(route('admin.settings'));
        $response->assertRedirect('/login');
    }

    /**
     * Non-admin users cannot access admin settings.
     */
    public function test_non_admin_cannot_access_admin_settings(): void
    {
        $user = User::where('email', 'manager@example.com')->first();
        if (!$user) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($user)->get(route('admin.settings'));
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Non-admin users cannot update settings.
     */
    public function test_non_admin_cannot_update_settings(): void
    {
        $user = User::where('email', 'manager@example.com')->first();
        if (!$user) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($user)->post(route('admin.settings.update'), [
            'system_name' => 'Hacked Project Tracker',
            'theme' => 'refined_indigo',
        ]);
        $response->assertRedirect(route('dashboard'));
        
        $this->assertNotEquals('Hacked Project Tracker', Setting::get('system_name'));
    }

    /**
     * Administrator can access settings page.
     */
    public function test_admin_can_access_settings(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($admin)->get(route('admin.settings'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings')
            ->has('systemRules')
        );
    }

    /**
     * Admin can update system settings.
     */
    public function test_admin_can_update_settings(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'system_name' => 'Awesome New Tracker',
            'theme' => 'modern_midnight',
        ]);

        $response->assertRedirect();
        
        $this->assertEquals('Awesome New Tracker', Setting::get('system_name'));
        $this->assertEquals('modern_midnight', Setting::get('theme'));
    }

    /**
     * Admin can store a new system rule.
     */
    public function test_admin_can_create_system_rule(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($admin)->post(route('admin.rules.store'), [
            'name' => 'Test Conditional Rule',
            'type' => 'conditional_logic',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Test rule description',
            'scope' => ['Projects'],
            'actions' => ['show_field'],
            'rule_logic' => [
                'conditions' => [
                    ['field' => 'Status', 'operator' => 'equals', 'value' => 'Completed']
                ]
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('system_rules', [
            'name' => 'Test Conditional Rule',
            'type' => 'conditional_logic',
        ]);
    }

    /**
     * Admin can update an existing system rule.
     */
    public function test_admin_can_update_system_rule(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $rule = SystemRule::first();
        if (!$admin || !$rule) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($admin)->put(route('admin.rules.update', $rule->id), [
            'name' => 'Updated System Rule Title',
            'type' => $rule->type,
            'enabled' => true,
            'status' => 'active',
            'description' => 'Updated rule rationale',
            'scope' => $rule->scope,
            'actions' => $rule->actions,
            'rule_logic' => $rule->rule_logic,
            'reason_for_change' => 'Updating rule name per spec requirement.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('system_rules', [
            'id' => $rule->id,
            'name' => 'Updated System Rule Title',
        ]);
    }

    /**
     * Admin can toggle a system rule enabled state.
     */
    public function test_admin_can_toggle_system_rule(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $rule = SystemRule::first();
        if (!$admin || !$rule) {
            $this->markTestSkipped('Seed data not available.');
        }

        $initialState = $rule->enabled;

        $response = $this->actingAs($admin)->post(route('admin.rules.toggle', $rule->id));
        $response->assertRedirect();

        $rule->refresh();
        $this->assertEquals(!$initialState, $rule->enabled);
    }

    /**
     * Admin can clone a system rule.
     */
    public function test_admin_can_clone_system_rule(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $rule = SystemRule::first();
        if (!$admin || !$rule) {
            $this->markTestSkipped('Seed data not available.');
        }

        $response = $this->actingAs($admin)->post(route('admin.rules.clone', $rule->id));
        $response->assertRedirect();

        $this->assertDatabaseHas('system_rules', [
            'name' => $rule->name . ' (Copy)',
            'status' => 'draft',
        ]);
    }

    /**
     * Admin can delete a system rule.
     */
    public function test_admin_can_delete_system_rule(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $rule = SystemRule::create([
            'name' => 'Rule To Delete',
            'type' => 'validation_rule',
            'enabled' => false,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.rules.destroy', $rule->id));
        $response->assertRedirect();

        $this->assertDatabaseMissing('system_rules', [
            'id' => $rule->id,
        ]);
    }
}
