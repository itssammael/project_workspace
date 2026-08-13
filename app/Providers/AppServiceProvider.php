<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\TaskBoard;

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

        Gate::define('view-admin-management', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkPageAccess($user, '/admin/management', ['admin'], ['admin_staff']);
        });

        Gate::define('view-admin-settings', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkPageAccess($user, '/admin/settings', ['admin'], ['admin_staff']);
        });

        Gate::define('view-support-function-reports', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkPageAccess($user, '/support-function-reports', ['admin', 'user', 'viewer'], ['admin_staff', 'department_head', 'project_manager', 'developer']);
        });

        Gate::define('edit-support-function-reports', function (User $user) {
            return \App\Services\SystemRuleEvaluator::canEditSupportFunctionReports($user);
        });

        Gate::define('manage-system-settings', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'manage_system_settings', ['admin'], ['admin_staff']);
        });

        Gate::define('manage-users', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'manage_users', ['admin'], ['admin_staff']);
        });

        Gate::define('manage-sections', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'manage_sections', ['admin'], []);
        });

        Gate::define('manage-departments', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'manage_departments', ['admin'], []);
        });

        Gate::define('manage-workflows', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'manage_workflows', ['admin'], []);
        });

        Gate::define('manage-functional-roles', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'manage_functional_roles', ['admin'], []);
        });

        Gate::define('delete-user', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'delete_user', ['admin'], []);
        });

        Gate::define('delete-task-board', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'delete_task_board', ['admin'], [])
                || \App\Services\SystemRuleEvaluator::checkOperation($user, 'delete_project', ['admin'], []);
        });
        Gate::define('delete-project', function (User $user) {
            return Gate::allows('delete-task-board');
        });

        // Functional/Production Role Gates
        Gate::define('create-task-boards', function (User $user) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'create_task_board', ['admin'], ['department_head'])
                || \App\Services\SystemRuleEvaluator::checkOperation($user, 'create_project', ['admin'], ['department_head']);
        });
        Gate::define('create-projects', function (User $user) {
            return Gate::allows('create-task-boards');
        });

        Gate::define('update-task-board', function (User $user, TaskBoard $taskBoard) {
            return \App\Services\SystemRuleEvaluator::checkOperation($user, 'update_task_board', ['admin'], ['project_manager', 'department_head'])
                || \App\Services\SystemRuleEvaluator::checkOperation($user, 'update_project', ['admin'], ['project_manager', 'department_head']);
        });
        Gate::define('update-project', function (User $user, TaskBoard $taskBoard) {
            return Gate::allows('update-task-board', $taskBoard);
        });

        Gate::define('manage-tasks', function (User $user, TaskBoard $taskBoard) {
            $isPermittedRole = \App\Services\SystemRuleEvaluator::checkOperation($user, 'manage_tasks', ['admin'], ['project_manager']);
            if (!$isPermittedRole) {
                return false;
            }
            if ($user->hasRole('admin')) {
                return true;
            }
            return $user->member && $taskBoard->section && $taskBoard->section->member_id === $user->member->id;
        });

        Gate::define('view-task-board', function (User $user, TaskBoard $taskBoard) {
            if ($user->hasRole('admin')) {
                return true;
            }
            return $user->member && $user->member->sections()->where('sections.id', $taskBoard->section_id)->exists();
        });
        Gate::define('view-project', function (User $user, TaskBoard $taskBoard) {
            return Gate::allows('view-task-board', $taskBoard);
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
