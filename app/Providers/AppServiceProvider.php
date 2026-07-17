<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Project;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin bypass
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
        });

        // System Role Gates
        Gate::define('admin', function (User $user) {
            return $user->hasRole('admin');
        });

        Gate::define('user', function (User $user) {
            return $user->hasRole('user');
        });

        Gate::define('viewer', function (User $user) {
            return $user->hasRole('viewer');
        });

        // Functional/Production Role Gates
        Gate::define('create-projects', function (User $user) {
            if (!$user->hasRole('user') && !$user->hasRole('admin')) {
                return false;
            }
            return $user->member && $user->member->memberRoles()->where('slug', 'department_head')->exists();
        });

        Gate::define('update-project', function (User $user, Project $project) {
            if (!$user->hasRole('user') && !$user->hasRole('admin')) {
                return false;
            }
            return $user->member && $user->member->memberRoles()->whereIn('slug', ['project_manager', 'department_head'])->exists();
        });

        Gate::define('manage-tasks', function (User $user, Project $project) {
            if (!$user->member || !$user->member->memberRoles()->where('slug', 'project_manager')->exists()) {
                return false;
            }
            return $project->section && $project->section->member_id === $user->member->id;
        });

        Gate::define('view-project', function (User $user, Project $project) {
            return $user->member && $user->member->sections()->where('sections.id', $project->section_id)->exists();
        });

        // Listen to login/logout events for activity logging
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function ($event) {
                \App\Models\SystemLog::create([
                    'user_id' => $event->user->id,
                    'user_name' => $event->user->name,
                    'action' => 'Login',
                    'description' => "User {$event->user->name} ({$event->user->email}) logged in.",
                    'ip_address' => request()->ip(),
                ]);
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Logout::class,
            function ($event) {
                if ($event->user) {
                    \App\Models\SystemLog::create([
                        'user_id' => $event->user->id,
                        'user_name' => $event->user->name,
                        'action' => 'Logout',
                        'description' => "User {$event->user->name} ({$event->user->email}) logged out.",
                        'ip_address' => request()->ip(),
                    ]);
                }
            }
        );
    }
}

