<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
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
        $response->assertStatus(403);
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
        $response->assertStatus(403);
        
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
        $response->assertInertia(fn ($page) => $page->component('Admin/Settings'));
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
}
