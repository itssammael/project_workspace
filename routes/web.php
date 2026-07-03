<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AdminManagementController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    // Tasks
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Admin Management
    Route::get('/admin/management', [AdminManagementController::class, 'index'])->name('admin.management');
    Route::post('/admin/users', [AdminManagementController::class, 'storeUser'])->name('admin.users.store');
    Route::post('/admin/users/bulk-destroy', [AdminManagementController::class, 'bulkDestroyUsers'])->name('admin.users.bulk-destroy');
    Route::put('/admin/users/{user}', [AdminManagementController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminManagementController::class, 'destroyUser'])->name('admin.users.destroy');
    Route::post('/admin/member-roles', [AdminManagementController::class, 'storeMemberRole'])->name('admin.member-roles.store');
    Route::delete('/admin/member-roles/{memberRole}', [AdminManagementController::class, 'destroyMemberRole'])->name('admin.member-roles.destroy');
    Route::post('/admin/teams', [AdminManagementController::class, 'storeTeam'])->name('admin.teams.store');
    Route::put('/admin/teams/{team}', [AdminManagementController::class, 'updateTeam'])->name('admin.teams.update');
    Route::delete('/admin/teams/{team}', [AdminManagementController::class, 'destroyTeam'])->name('admin.teams.destroy');
    Route::post('/admin/phases', [AdminManagementController::class, 'storePhase'])->name('admin.phases.store');
    Route::put('/admin/phases/{phase}', [AdminManagementController::class, 'updatePhase'])->name('admin.phases.update');
    Route::delete('/admin/phases/{phase}', [AdminManagementController::class, 'destroyPhase'])->name('admin.phases.destroy');
    Route::post('/admin/phases/bulk-assign', [AdminManagementController::class, 'bulkAssignPhases'])->name('admin.phases.bulk-assign');
    Route::post('/admin/dev-types', [AdminManagementController::class, 'storeDevelopmentType'])->name('admin.dev-types.store');
    Route::put('/admin/dev-types/{developmentType}', [AdminManagementController::class, 'updateDevelopmentType'])->name('admin.dev-types.update');
    Route::delete('/admin/dev-types/{developmentType}', [AdminManagementController::class, 'destroyDevelopmentType'])->name('admin.dev-types.destroy');

    // Admin Settings
    Route::get('/admin/settings', [\App\Http\Controllers\AdminSettingsController::class, 'index'])->name('admin.settings');
    Route::post('/admin/settings', [\App\Http\Controllers\AdminSettingsController::class, 'update'])->name('admin.settings.update');
});
