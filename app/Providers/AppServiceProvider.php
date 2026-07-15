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
            return $user->member && $user->member->memberRoles()->where('slug', 'department_head')->exists();
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
    }
}

