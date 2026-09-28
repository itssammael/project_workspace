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
            ['name' => 'Task Board Membership & Visibility Guard'],
            [
                'type' => 'role_permission_rule',
                'enabled' => true,
                'status' => 'active',
                'description' => 'System-wide workspace governance rule specifying that all users can only view Task Boards where they are an assigned member or collaborator. Users with functional role Section Head can view all Task Boards within the section they belong to. System Administrators and Department Heads retain full cross-sectional view capabilities.',
                'scope' => ['Task Boards', 'Dashboard'],
                'actions' => ['filter_data_scope', 'reject_submission'],
                'rule_logic' => [
                    'category' => 'workspace_governance',
                    'operation' => 'view_task_board',
                    'target' => 'task_boards',
                    'allowed_view_scope' => 'member_or_collaborator',
                    'exception_functional_roles' => ['section-head', 'section_head'],
                    'exception_scope' => 'assigned_sections',
                    'bypass_system_roles' => ['admin'],
                    'bypass_functional_roles' => ['department_head'],
                    'error_message' => 'Users can only view task boards where they are an assigned member or collaborator, unless they are a Section Head for this section or a Department Head/Administrator.',
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
        SystemRule::where('name', 'Task Board Membership & Visibility Guard')->delete();
    }
};
