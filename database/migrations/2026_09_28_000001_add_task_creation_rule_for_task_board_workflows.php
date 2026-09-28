<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SystemRule;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        SystemRule::updateOrCreate(
            ['name' => 'Task Creation in Task Board Workflows'],
            [
                'type' => 'role_permission_rule',
                'enabled' => true,
                'status' => 'active',
                'description' => 'System Engine rule permitting Users with System Role "User" and Functional Role "Project Manager" to create, update, and delete tasks within Task Board Workflows only if the user is a collaborator or assigned member of the taskboard.',
                'scope' => ['Tasks', 'Task Boards', 'Workflows'],
                'actions' => ['enable_field', 'reject_submission'],
                'rule_logic' => [
                    'category' => 'workspace_governance',
                    'operation' => 'manage_tasks',
                    'target' => 'task_board_workflows',
                    'allowed_system_roles' => ['admin', 'user'],
                    'allowed_functional_roles' => ['project_manager'],
                    'requires_taskboard_membership' => true,
                    'constraint' => 'must_be_board_collaborator_or_assigned_member',
                    'allow_task_board_project_manager' => true,
                    'allow_creator' => true,
                    'permissions' => ['create_task', 'update_task', 'delete_task'],
                    'error_message' => 'Tasks can only be created, updated, or deleted in Task Board Workflows if the user is a collaborator or assigned member of the taskboard.',
                ],
                'created_by' => 'System Security Architect',
                'last_modified_by' => 'System Security Architect',
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SystemRule::where('name', 'Task Creation in Task Board Workflows')->delete();
    }
};
