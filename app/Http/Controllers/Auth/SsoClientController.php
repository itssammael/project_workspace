<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SsoIdentityLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SsoClientController extends Controller
{
    /**
     * Redirect user to Login Portal SSO endpoint.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $baseUrl = config('services.login_portal.url') ?: 'http://127.0.0.1:8000';
        $clientId = config('services.login_portal.client_id') ?: 'project_tracker_client_id';
        $redirectUri = config('services.login_portal.redirect_uri') ?: route('sso.callback');


        $state = \Illuminate\Support\Str::random(40);
        $codeVerifier = \Illuminate\Support\Str::random(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        session([
            'sso_state' => $state,
            'sso_code_verifier' => $codeVerifier,
        ]);

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        $authorizeUrl = rtrim($baseUrl, '/') . '/sso/authorize?' . $params;

        return redirect()->away($authorizeUrl);
    }

    /**
     * Handle authorization callback from Login Portal SSO.
     */
    public function callback(Request $request): RedirectResponse
    {
        $sessionState = session('sso_state');
        $codeVerifier = session('sso_code_verifier');
        session()->forget(['sso_state', 'sso_code_verifier']);

        $state = $request->query('state');
        $code = $request->query('code');

        // Validate state parameter if state was initiated by client session (SP-initiated flow)
        if (! empty($sessionState) && ! empty($state) && ! hash_equals($sessionState, $state)) {
            Log::warning('SSO callback failed: Invalid or expired state parameter.');
            return redirect()->route('login')->withErrors(['email' => 'Single Sign-On authorization failed due to invalid state. Please try again.']);
        }


        if (empty($code)) {
            $errorDesc = $request->query('error_description', 'Authorization request was declined.');
            return redirect()->route('login')->withErrors(['email' => 'SSO error: ' . $errorDesc]);
        }

        $baseUrl = config('services.login_portal.url') ?: 'http://127.0.0.1:8000';
        $clientId = config('services.login_portal.client_id') ?: 'project_tracker_client_id';
        $clientSecret = config('services.login_portal.client_secret') ?: 'project_tracker_client_secret';
        $redirectUri = config('services.login_portal.redirect_uri') ?: route('sso.callback');


        try {
            $tokenEndpoint = rtrim($baseUrl, '/') . '/api/sso/token';
            $response = Http::asForm()->timeout(10)->post($tokenEndpoint, [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $codeVerifier,
            ]);

            if (! $response->successful()) {
                Log::error('SSO token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return redirect()->route('login')->withErrors(['email' => 'Failed to authenticate with Login Portal.']);
            }

            $data = $response->json();
            $ssoUser = $data['user'] ?? null;

            if (! $ssoUser || empty($ssoUser['id']) || empty($ssoUser['email'])) {
                Log::error('SSO payload missing user details', ['payload' => $data]);
                return redirect()->route('login')->withErrors(['email' => 'Invalid identity response received from Login Portal.']);
            }

            $localUser = $this->resolveLocalUser($ssoUser);

            Auth::login($localUser, true);
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        } catch (\Throwable $e) {
            Log::error('SSO authentication exception: ' . $e->getMessage());
            return redirect()->route('login')->withErrors(['email' => 'Unable to connect to Login Portal server.']);
        }
    }

    /**
     * Resolve or link local User from SSO identity payload.
     */
    protected function resolveLocalUser(array $ssoUser): User
    {
        $portalUserId = (string) $ssoUser['id'];

        $boundUserId = $ssoUser['bound_user_id'] ?? null;
        $boundUsername = $ssoUser['bound_username'] ?? null;

        // Case 1: Match directly by explicit bound local user ID
        if ($boundUserId) {
            $user = User::find($boundUserId);
            if ($user) {
                SsoIdentityLink::updateOrCreate(
                    ['provider' => 'login_portal', 'login_portal_user_id' => $portalUserId],
                    ['user_id' => $user->id]
                );
                return $user;
            }
        }

        // Case 2: Check existing SSO identity link
        $link = SsoIdentityLink::where('provider', 'login_portal')
            ->where('login_portal_user_id', $portalUserId)
            ->first();

        if ($link && $link->user) {
            return $link->user;
        }

        // Case 3: Match existing local user by bound username or email
        $identifier = $boundUsername ?: $ssoUser['email'];
        $user = User::where('email', $identifier)->orWhere('username', $identifier)->first();

        if ($user) {
            SsoIdentityLink::updateOrCreate(
                ['provider' => 'login_portal', 'login_portal_user_id' => $portalUserId],
                ['user_id' => $user->id]
            );

            return $user;
        }


        // Case 3: Provision new local user safely
        $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $ssoUser['email'])[0])) ?: 'user';
        $username = $baseUsername;
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter++;
        }

        $standardRole = Role::where('slug', 'member')->orWhere('slug', 'user')->first();

        $user = User::create([
            'name' => $ssoUser['name'],
            'username' => $username,
            'email' => $ssoUser['email'],
            'password' => Hash::make(\Illuminate\Support\Str::random(32)),
            'role_id' => $standardRole?->id,
            'email_verified_at' => ! empty($ssoUser['email_verified_at']) ? now() : null,
        ]);

        SsoIdentityLink::create([
            'user_id' => $user->id,
            'provider' => 'login_portal',
            'login_portal_user_id' => $portalUserId,
        ]);

        return $user;
    }
}
