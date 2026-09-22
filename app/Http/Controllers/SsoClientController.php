<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SsoClientController extends Controller
{
    /**
     * Redirect user to the Login Portal authorization endpoint.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('sso_state', $state);

        $authUrl = rtrim(config('services.login_portal.url'), '/').'/sso/authorize?'.http_build_query([
            'client_id' => config('services.login_portal.client_id'),
            'redirect_uri' => config('services.login_portal.redirect_uri'),
            'response_type' => 'code',
            'state' => $state,
        ]);

        return redirect()->away($authUrl);
    }

    /**
     * Handle the authorization callback from Login Portal.
     */
    public function callback(Request $request): RedirectResponse
    {
        $sessionState = $request->session()->pull('sso_state');
        $state = $request->query('state');

        // Verify state only when SSO was initiated by the client (SP-initiated flow)
        if ($sessionState && $state && ! hash_equals($sessionState, $state)) {
            return redirect()->route('login')->withErrors(['email' => 'Single Sign-On failed: Invalid state parameter.']);
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect()->route('login')->withErrors(['email' => 'Single Sign-On failed: Missing authorization code.']);
        }

        $tokenUrl = rtrim(config('services.login_portal.url'), '/').'/api/sso/token';

        $response = Http::asForm()->post($tokenUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.login_portal.client_id'),
            'client_secret' => config('services.login_portal.client_secret'),
            'redirect_uri' => config('services.login_portal.redirect_uri'),
            'code' => $code,
        ]);

        if (! $response->successful()) {
            Log::error('SSO Token Exchange Failed', [
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);

            return redirect()->route('login')->withErrors(['email' => 'Failed to authenticate with Login Portal.']);
        }

        $userData = $response->json('user') ?? $response->json('data.user');
        if (! $userData || empty($userData['id']) || empty($userData['email'])) {
            return redirect()->route('login')->withErrors(['email' => 'Invalid user data received from Login Portal.']);
        }

        // 1. Resolve by bound external user id (if account was bound via Login Portal)
        $user = null;
        if (! empty($userData['bound_user_id'])) {
            $user = User::find($userData['bound_user_id']);
        }

        // 2. Resolve by bound external username
        if (! $user && ! empty($userData['bound_username'])) {
            $user = User::where('username', $userData['bound_username'])->first();
        }

        // 3. Fallback: resolve by login_portal_user_id or email
        if (! $user) {
            $user = User::where('login_portal_user_id', $userData['id'])
                ->orWhere('email', $userData['email'])
                ->first();
        }

        if (! $user) {
            $baseUsername = ! empty($userData['bound_username'])
                ? $userData['bound_username']
                : (! empty($userData['username'])
                    ? $userData['username']
                    : (strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $userData['email'])[0])) ?: 'user'));
            $username = $baseUsername;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername.$counter++;
            }

            $user = User::create([
                'name' => $userData['name'] ?? $username,
                'email' => $userData['email'],
                'username' => $username,
                'login_portal_user_id' => $userData['id'],
                'sso_provider' => 'login_portal',
                'password' => bcrypt(Str::random(32)),
            ]);
        } else {
            $user->update([
                'login_portal_user_id' => $userData['id'],
                'sso_provider' => 'login_portal',
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }
}
