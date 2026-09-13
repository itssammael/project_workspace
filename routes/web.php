<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TaskBoardController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AdminManagementController;

Route::get('/', function () {
    return redirect()->route('login');
});

// SSO Routes
Route::get('/sso/redirect', [\App\Http\Controllers\Auth\SsoClientController::class, 'redirect'])->name('sso.redirect');
Route::get('/sso/callback', [\App\Http\Controllers\Auth\SsoClientController::class, 'callback'])->name('sso.callback');

Route::middleware([

    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Task Boards
    Route::get('/task-boards', [TaskBoardController::class, 'index'])->name('task-boards.index');
    Route::get('/task-boards/create', [TaskBoardController::class, 'create'])->name('task-boards.create');
    Route::post('/task-boards', [TaskBoardController::class, 'store'])->name('task-boards.store');
    Route::get('/task-boards/{taskBoard}', [TaskBoardController::class, 'show'])->name('task-boards.show');
    Route::put('/task-boards/{taskBoard}', [TaskBoardController::class, 'update'])->name('task-boards.update');
    Route::delete('/task-boards/{taskBoard}', [TaskBoardController::class, 'destroy'])->name('task-boards.destroy');

    // Legacy Route Aliases for Projects
    Route::get('/projects', [TaskBoardController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [TaskBoardController::class, 'create'])->name('projects.create');
    Route::post('/projects', [TaskBoardController::class, 'store'])->name('projects.store');
    Route::get('/projects/{taskBoard}', [TaskBoardController::class, 'show'])->name('projects.show');
    Route::put('/projects/{taskBoard}', [TaskBoardController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{taskBoard}', [TaskBoardController::class, 'destroy'])->name('projects.destroy');

    // Tasks
    Route::post('/task-boards/{taskBoard}/tasks', [TaskController::class, 'store'])->name('task-boards.tasks.store');
    Route::post('/projects/{taskBoard}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::put('/tasks/{task}/move', [TaskController::class, 'moveKanbanCard'])->name('tasks.move');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Subtasks
    Route::put('/subtasks/{subTask}/status', [TaskController::class, 'updateSubtaskStatus'])->name('subtasks.update-status');
    Route::post('/subtasks/{subTask}/attachments', [TaskController::class, 'storeAttachment'])->name('subtasks.store-attachment');
    Route::post('/subtasks/{subTask}/comments', [TaskController::class, 'storeComment'])->name('subtasks.store-comment');

    // Support Function Reports
    Route::get('/support-function-reports', [App\Http\Controllers\SupportFunctionReportsController::class, 'index'])->name('support-function-reports.index');
    Route::post('/support-function-reports/attendance', [App\Http\Controllers\SupportFunctionReportsController::class, 'updateAttendance'])->name('support-function-reports.update-attendance');
    Route::post('/support-function-reports/tardiness-undertime', [App\Http\Controllers\SupportFunctionReportsController::class, 'updateTardinessAbsenceUndertime'])->name('support-function-reports.update-tardiness-undertime');
    Route::post('/support-function-reports/activities', [App\Http\Controllers\SupportFunctionReportsController::class, 'storeActivity'])->name('support-function-reports.activities.store');
    Route::put('/support-function-reports/activities/{scheduledActivity}', [App\Http\Controllers\SupportFunctionReportsController::class, 'updateActivity'])->name('support-function-reports.activities.update');
    Route::delete('/support-function-reports/activities/{scheduledActivity}', [App\Http\Controllers\SupportFunctionReportsController::class, 'destroyActivity'])->name('support-function-reports.activities.destroy');

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
    Route::post('/admin/departments', [AdminManagementController::class, 'storeDepartment'])->name('admin.departments.store');
    Route::put('/admin/departments/{department}', [AdminManagementController::class, 'updateDepartment'])->name('admin.departments.update');
    Route::delete('/admin/departments/{department}', [AdminManagementController::class, 'destroyDepartment'])->name('admin.departments.destroy');
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
