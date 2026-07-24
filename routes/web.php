<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AdminManagementController;

Route::get('/', function () {
    return redirect()->route('login');
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
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Tasks
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::put('/tasks/{task}/move', [TaskController::class, 'moveKanbanCard'])->name('tasks.move');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Subtasks
    Route::put('/subtasks/{subTask}/status', [TaskController::class, 'updateSubtaskStatus'])->name('subtasks.update-status');
    Route::post('/subtasks/{subTask}/attachments', [TaskController::class, 'storeAttachment'])->name('subtasks.store-attachment');
    Route::post('/subtasks/{subTask}/comments', [TaskController::class, 'storeComment'])->name('subtasks.store-comment');

    // Admin Management
    Route::get('/admin/management', [AdminManagementController::class, 'index'])->name('admin.management');
    Route::post('/admin/users', [AdminManagementController::class, 'storeUser'])->name('admin.users.store');
    Route::post('/admin/users/bulk-destroy', [AdminManagementController::class, 'bulkDestroyUsers'])->name('admin.users.bulk-destroy');
    Route::put('/admin/users/{user}', [AdminManagementController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminManagementController::class, 'destroyUser'])->name('admin.users.destroy');
    Route::post('/admin/member-roles', [AdminManagementController::class, 'storeMemberRole'])->name('admin.member-roles.store');
    Route::put('/admin/member-roles/{memberRole}', [AdminManagementController::class, 'updateMemberRole'])->name('admin.member-roles.update');
    Route::delete('/admin/member-roles/{memberRole}', [AdminManagementController::class, 'destroyMemberRole'])->name('admin.member-roles.destroy');
    Route::post('/admin/sections', [AdminManagementController::class, 'storeSection'])->name('admin.sections.store');
    Route::put('/admin/sections/{section}', [AdminManagementController::class, 'updateSection'])->name('admin.sections.update');
    Route::delete('/admin/sections/{section}', [AdminManagementController::class, 'destroySection'])->name('admin.sections.destroy');
    Route::post('/admin/workflows', [AdminManagementController::class, 'storeWorkflow'])->name('admin.workflows.store');
    Route::put('/admin/workflows/{workflow}', [AdminManagementController::class, 'updateWorkflow'])->name('admin.workflows.update');
    Route::delete('/admin/workflows/{workflow}', [AdminManagementController::class, 'destroyWorkflow'])->name('admin.workflows.destroy');
    Route::post('/admin/workflows/bulk-assign', [AdminManagementController::class, 'bulkAssignWorkflows'])->name('admin.workflows.bulk-assign');
    Route::post('/admin/workflow-types', [AdminManagementController::class, 'storeWorkflowType'])->name('admin.workflow-types.store');
    Route::put('/admin/workflow-types/{workflowType}', [AdminManagementController::class, 'updateWorkflowType'])->name('admin.workflow-types.update');
    Route::delete('/admin/workflow-types/{workflowType}', [AdminManagementController::class, 'destroyWorkflowType'])->name('admin.workflow-types.destroy');

    // Add role to member
    Route::post('/admin/members/{member}/attach-role', [AdminManagementController::class, 'attachRoleToMember'])->name('admin.members.attach-role');

    // Admin Settings
    Route::get('/admin/settings', [\App\Http\Controllers\AdminSettingsController::class, 'index'])->name('admin.settings');
    Route::post('/admin/settings', [\App\Http\Controllers\AdminSettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('/admin/settings/rules', [\App\Http\Controllers\AdminSettingsController::class, 'storeRule'])->name('admin.rules.store');
    Route::put('/admin/settings/rules/{rule}', [\App\Http\Controllers\AdminSettingsController::class, 'updateRule'])->name('admin.rules.update');
    Route::post('/admin/settings/rules/{rule}/toggle', [\App\Http\Controllers\AdminSettingsController::class, 'toggleRule'])->name('admin.rules.toggle');
    Route::post('/admin/settings/rules/{rule}/clone', [\App\Http\Controllers\AdminSettingsController::class, 'cloneRule'])->name('admin.rules.clone');
    Route::delete('/admin/settings/rules/{rule}', [\App\Http\Controllers\AdminSettingsController::class, 'destroyRule'])->name('admin.rules.destroy');
    Route::post('/admin/settings/rules/import', [\App\Http\Controllers\AdminSettingsController::class, 'importRules'])->name('admin.rules.import');
});
