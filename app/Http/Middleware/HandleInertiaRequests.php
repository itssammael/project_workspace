<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($user = $request->user()) {
            $user->loadMissing(['role', 'member.sections', 'member.memberRoles']);
        }

        return [
            ...parent::share($request),
            'settings' => [
                'system_name' => \Illuminate\Support\Facades\Schema::hasTable('settings') ? \App\Models\Setting::get('system_name', 'Project Tracker') : 'Project Tracker',
                'theme' => \Illuminate\Support\Facades\Schema::hasTable('settings') ? \App\Models\Setting::get('theme', 'corporate_teal') : 'corporate_teal',
                'logo' => \Illuminate\Support\Facades\Schema::hasTable('settings') ? \App\Models\Setting::get('logo', null) : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
