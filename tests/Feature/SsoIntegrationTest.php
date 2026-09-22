<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.login_portal.url' => 'http://lguportal',
            'services.login_portal.client_id' => 'client_GRfVtZokwTyt3nd7',
            'services.login_portal.client_secret' => 'ZAyiS6OEqFFMjRpzarc4yEkDDrHgGwq6sMNrBednhoKQYGJo3QVxPETW22TM0GLQ',
            'services.login_portal.redirect_uri' => 'http://teamtracker/sso/callback',
        ]);
    }

    public function test_sso_redirect_generates_session_state_and_redirects_to_authorization_endpoint(): void
    {
        $response = $this->get(route('sso.redirect'));

        $response->assertRedirect();
        $targetUrl = $response->headers->get('Location');

        $this->assertStringContainsString('http://lguportal/sso/authorize', $targetUrl);
        $this->assertStringContainsString('client_id=client_GRfVtZokwTyt3nd7', $targetUrl);
        $this->assertStringContainsString('redirect_uri='.urlencode('http://teamtracker/sso/callback'), $targetUrl);
        $this->assertStringContainsString('response_type=code', $targetUrl);
        $this->assertStringContainsString('state=', $targetUrl);

        $this->assertTrue(session()->has('sso_state'));
    }

    public function test_sso_callback_rejects_invalid_state(): void
    {
        session(['sso_state' => 'valid_state_123']);

        $response = $this->get(route('sso.callback', [
            'state' => 'invalid_state_456',
            'code' => 'auth_code_xyz',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(Auth::check());
    }

    public function test_sso_callback_rejects_missing_code(): void
    {
        session(['sso_state' => 'valid_state_123']);

        $response = $this->get(route('sso.callback', [
            'state' => 'valid_state_123',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(Auth::check());
    }

    public function test_sso_callback_provisions_new_user_on_successful_token_exchange(): void
    {
        session(['sso_state' => 'valid_state_123']);

        Http::fake([
            'http://lguportal/api/sso/token' => Http::response([
                'user' => [
                    'id' => 999123,
                    'name' => 'Jane SSO User',
                    'email' => 'jane.sso@example.com',
                ],
            ], 200),
        ]);

        $response = $this->get(route('sso.callback', [
            'state' => 'valid_state_123',
            'code' => 'valid_auth_code_999',
        ]));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(Auth::check());

        $this->assertDatabaseHas('users', [
            'email' => 'jane.sso@example.com',
            'login_portal_user_id' => 999123,
            'sso_provider' => 'login_portal',
        ]);
    }

    public function test_sso_callback_matches_existing_user_by_email_and_updates_sso_id(): void
    {
        $user = User::factory()->create([
            'name' => 'Existing Local User',
            'email' => 'existing.user@example.com',
            'login_portal_user_id' => null,
        ]);

        session(['sso_state' => 'valid_state_123']);

        Http::fake([
            'http://lguportal/api/sso/token' => Http::response([
                'user' => [
                    'id' => 888456,
                    'name' => 'Existing Local User',
                    'email' => 'existing.user@example.com',
                ],
            ], 200),
        ]);

        $response = $this->get(route('sso.callback', [
            'state' => 'valid_state_123',
            'code' => 'valid_auth_code_888',
        ]));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'existing.user@example.com',
            'login_portal_user_id' => 888456,
        ]);
    }

    public function test_sso_callback_matches_existing_user_by_login_portal_user_id(): void
    {
        $user = User::factory()->create([
            'email' => 'old.email@example.com',
            'login_portal_user_id' => 777111,
        ]);

        session(['sso_state' => 'valid_state_123']);

        Http::fake([
            'http://lguportal/api/sso/token' => Http::response([
                'user' => [
                    'id' => 777111,
                    'name' => 'Renamed User',
                    'email' => 'new.email@example.com',
                ],
            ], 200),
        ]);

        $response = $this->get(route('sso.callback', [
            'state' => 'valid_state_123',
            'code' => 'valid_auth_code_777',
        ]));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }
}
