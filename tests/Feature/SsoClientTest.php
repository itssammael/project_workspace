<?php

namespace Tests\Feature;

use App\Models\SsoIdentityLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.login_portal.url' => 'http://127.0.0.1:8000',
            'services.login_portal.client_id' => 'project_tracker_client_id',
            'services.login_portal.client_secret' => 'project_tracker_client_secret',
            'services.login_portal.redirect_uri' => 'http://127.0.0.1:8002/sso/callback',
        ]);
    }

    public function test_sso_redirect_generates_session_state_and_redirects_to_portal(): void
    {
        $response = $this->get(route('sso.redirect'));

        $response->assertRedirect();
        $targetUrl = $response->headers->get('Location');
        $this->assertStringContainsString('http://127.0.0.1:8000/sso/authorize', $targetUrl);
        $this->assertStringContainsString('client_id=project_tracker_client_id', $targetUrl);
        $this->assertStringContainsString('code_challenge=', $targetUrl);

        $this->assertTrue(session()->has('sso_state'));
        $this->assertTrue(session()->has('sso_code_verifier'));
    }

    public function test_sso_callback_rejects_invalid_state(): void
    {
        session(['sso_state' => 'valid_state_123']);

        $response = $this->get(route('sso.callback', [
            'state' => 'wrong_state_456',
            'code' => 'some_code',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(auth()->check());
    }

    public function test_sso_callback_authenticates_existing_linked_user(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        SsoIdentityLink::create([
            'user_id' => $user->id,
            'provider' => 'login_portal',
            'login_portal_user_id' => 'portal_user_5005',
        ]);

        session([
            'sso_state' => 'valid_state',
            'sso_code_verifier' => 'verifier_123',
        ]);

        Http::fake([
            'http://127.0.0.1:8000/api/sso/token' => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'mocked_token',
                'user' => [
                    'id' => 'portal_user_5005',
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                ],
            ], 200),
        ]);

        $response = $this->get(route('sso.callback', [
            'state' => 'valid_state',
            'code' => 'valid_auth_code',
        ]));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(auth()->check());
        $this->assertEquals($user->id, auth()->id());
    }

    public function test_sso_callback_links_existing_local_user_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'existing_pt@example.com',
        ]);

        session([
            'sso_state' => 'valid_state',
            'sso_code_verifier' => 'verifier_123',
        ]);

        Http::fake([
            'http://127.0.0.1:8000/api/sso/token' => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'mocked_token',
                'user' => [
                    'id' => 'portal_user_6006',
                    'name' => 'Existing Tracker User',
                    'email' => 'existing_pt@example.com',
                ],
            ], 200),
        ]);

        $response = $this->get(route('sso.callback', [
            'state' => 'valid_state',
            'code' => 'valid_auth_code',
        ]));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(auth()->check());
        $this->assertEquals($user->id, auth()->id());

        $this->assertDatabaseHas('sso_identity_links', [
            'user_id' => $user->id,
            'login_portal_user_id' => 'portal_user_6006',
        ]);
    }

    public function test_sso_callback_provisions_new_user(): void
    {
        session([
            'sso_state' => 'valid_state',
            'sso_code_verifier' => 'verifier_123',
        ]);

        Http::fake([
            'http://127.0.0.1:8000/api/sso/token' => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'mocked_token',
                'user' => [
                    'id' => 'portal_user_7007',
                    'name' => 'New Tracker SSO User',
                    'email' => 'newtrackersso@example.com',
                ],
            ], 200),
        ]);

        $response = $this->get(route('sso.callback', [
            'state' => 'valid_state',
            'code' => 'valid_auth_code',
        ]));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(auth()->check());

        $this->assertDatabaseHas('users', [
            'email' => 'newtrackersso@example.com',
        ]);

        $this->assertDatabaseHas('sso_identity_links', [
            'login_portal_user_id' => 'portal_user_7007',
        ]);
    }

    public function test_idp_initiated_sso_callback_authenticates_user(): void
    {
        $user = User::factory()->create([
            'email' => 'idp_pt_user@example.com',
        ]);

        Http::fake([
            'http://127.0.0.1:8000/api/sso/token' => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'mocked_token',
                'user' => [
                    'id' => 'portal_user_9009',
                    'bound_user_id' => (string) $user->id,
                    'name' => 'IdP Tracker User',
                    'email' => 'idp_pt_user@example.com',
                ],
            ], 200),
        ]);

        // Simulating IdP-initiated redirect from Login Portal Dashboard (no session sso_state pre-set)
        $response = $this->get(route('sso.callback', [
            'state' => 'idp_state_456',
            'code' => 'idp_auth_code',
        ]));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(auth()->check());
        $this->assertEquals($user->id, auth()->id());
    }

    public function test_existing_local_login_still_works(): void
    {
        $user = User::factory()->create([
            'username' => 'ptuser',
            'email' => 'ptlocal@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'ptlocal@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertTrue(auth()->check());
        $this->assertEquals($user->id, auth()->id());
    }
}

