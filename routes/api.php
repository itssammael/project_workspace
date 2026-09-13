<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/sso/verify-credentials', function (Request $request) {
    $clientSecret = config('services.login_portal.client_secret') ?: 'project_tracker_client_secret';
    if ($request->input('client_secret') !== $clientSecret) {
        return response()->json(['success' => false, 'message' => 'Invalid client secret.'], 401);
    }

    $username = $request->input('username');
    $password = $request->input('password');

    if (! $username || ! $password) {
        return response()->json(['success' => false, 'message' => 'Username/Email and Password are required.'], 400);
    }

    $user = \App\Models\User::where('email', $username)->orWhere('username', $username)->first();

    if (! $user || ! \Illuminate\Support\Facades\Hash::check($password, $user->password)) {
        return response()->json(['success' => false, 'message' => 'Invalid username or password.'], 400);
    }

    return response()->json([
        'success' => true,
        'user' => [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
        ],
    ]);
});

