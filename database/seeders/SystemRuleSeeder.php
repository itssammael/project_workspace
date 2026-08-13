<?php

namespace Database\Seeders;

use App\Models\SystemRule;
use Illuminate\Database\Seeder;

class SystemRuleSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing system rules to eliminate old entries
        SystemRule::truncate();

        // =========================================================
        // DOMAIN 1: SYSTEM-WIDE ACCESS & SECURITY RULES
        // =========================================================

        // Rule 1: Admin Management Page Access
        SystemRule::create([
            'name' => 'Admin Management Page View Access Restriction',
            'type' => 'page_access_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide page view restriction enforcing that access to the Admin Management dashboard and User/Member provisioning module is restricted to System Administrators and users with Functional Role Admin Staff.',
            'scope' => ['System Settings', 'Admin Management'],
            'actions' => ['route_for_approval', 'reject_submission'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'target_route' => '/admin/management',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => ['admin_staff'],
                'restriction_action' => 'block_and_redirect',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 2: System Settings & Rules Engine Access
        SystemRule::create([
            'name' => 'System Settings & Rules Engine View Access',
            'type' => 'page_access_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide page view restriction controlling access to System Settings, Branding palettes, and System Rules Engine configuration.',
            'scope' => ['System Settings'],
            'actions' => ['route_for_approval', 'reject_submission'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'target_route' => '/admin/settings',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => ['admin_staff'],
                'restriction_action' => 'block_and_redirect',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 3: Admin Staff User Provisioning Limitation
        SystemRule::create([
            'name' => 'Admin Staff System Role Provisioning Guard',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide role action restriction specifying that non-administrator Admin Staff users are only permitted to provision new users with System Role User.',
            'scope' => ['Users'],
            'actions' => ['reject_submission', 'show_error_message'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'create_user',
                'enforced_for_functional_role' => 'admin_staff',
                'allowed_system_role_slug' => 'user',
                'prohibited_system_role_slugs' => ['admin', 'viewer'],
                'error_message' => 'Admin Staff can only create new users with System Role User.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 4: Administrator & Department Head Profile Guard
        SystemRule::create([
            'name' => 'Administrator & Department Head Profile Guard',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide profile protection guard prohibiting Admin Staff users from modifying or updating profile details, email addresses, or role assignments of System Administrators or Department Heads.',
            'scope' => ['Users'],
            'actions' => ['disable_field', 'reject_submission'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'update_user',
                'enforced_for_functional_role' => 'admin_staff',
                'protected_system_roles' => ['admin'],
                'protected_functional_roles' => ['department_head'],
                'error_message' => 'Admin Staff cannot update details of users with System Role Administrator or Functional Role Department Head.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 5: User Account Deletion Protection
        SystemRule::create([
            'name' => 'User Account Deletion Authority Restriction',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide operational restriction reserving user account and member profile deletion capabilities exclusively to System Administrators.',
            'scope' => ['Users'],
            'actions' => ['hide_field', 'reject_submission'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'delete_user',
                'allowed_system_roles' => ['admin'],
                'prohibited_functional_roles' => ['admin_staff', 'department_head', 'project_manager', 'developer'],
                'error_message' => 'Only System Administrators are authorized to delete user accounts.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 6: Section Scope Assignment Rule
        SystemRule::create([
            'name' => 'Admin Staff Section Scope Assignment Limit',
            'type' => 'data_integrity_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide scope constraint restricting Admin Staff users to assigning newly provisioned or updated users only to organizational sections that the staff member belongs to.',
            'scope' => ['Users', 'Sections'],
            'actions' => ['reject_submission', 'show_error_message'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'assign_section',
                'enforced_for_functional_role' => 'admin_staff',
                'constraint' => 'must_belong_to_section',
                'error_message' => 'You can only assign users to sections you belong to.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 7: Same-Department Member Access & Search Visibility Security Rule
        SystemRule::create([
            'name' => 'Same-Department Member Access & Visibility Guard',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide security rule specifying that users (depending on System/Functional Role permissions) can only access, view, list, and search Members and Users that belong under the same Department.',
            'scope' => ['Users', 'Sections', 'Departments'],
            'actions' => ['filter_data_scope', 'reject_submission'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'same_department_member_access',
                'enforce_same_department' => true,
                'bypass_system_roles' => ['admin'],
                'allowed_functional_roles' => [],
                'error_message' => 'Users can only view and access members belonging to the same department.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 8: Support Function Reports Page Access Restriction
        SystemRule::create([
            'name' => 'Support Function Reports Page Access Restriction',
            'type' => 'page_access_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide page view restriction enforcing that access to Support Function Reports is restricted only to users with Employee Types Regular or Casual.',
            'scope' => ['Support Function Reports'],
            'actions' => ['route_for_approval', 'reject_submission'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'target_route' => '/support-function-reports',
                'allowed_system_roles' => ['admin', 'user', 'viewer'],
                'allowed_functional_roles' => ['admin_staff', 'department_head', 'project_manager', 'developer'],
                'allowed_employee_types' => ['Regular', 'Casual'],
                'restriction_action' => 'block_and_redirect',
                'error_message' => 'Support Function Reports are only accessible to Regular and Casual employees.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // =========================================================
        // DOMAIN 2: WORKSPACE & FORM GOVERNANCE RULES
        // =========================================================

        // Rule 7: Task Board Creation Authority
        SystemRule::create([
            'name' => 'Task Board Creation Authority Rule',
            'type' => 'approval_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Workspace governance rule restricting task board creation capabilities to System Administrators and Department Heads.',
            'scope' => ['Task Boards'],
            'actions' => ['route_for_approval', 'reject_submission'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'operation' => 'create_project',
                'approval_chain' => ['Department Head', 'Administrator'],
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => ['department_head'],
                'error_message' => 'Only Department Heads and Administrators are authorized to create new task boards.',
            ],
            'created_by' => 'Workspace Governance Board',
            'last_modified_by' => 'Workspace Governance Board',
        ]);

        // Rule 8: Mandatory Client Feedback & Sign-off
        SystemRule::create([
            'name' => 'Infrastructure Task Board Sign-off Documentation',
            'type' => 'conditional_logic',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Conditional logic rule mandating that all completed Infrastructure task boards display required sign-off form and drawing attachments.',
            'scope' => ['Task Boards'],
            'actions' => ['show_field', 'hide_field'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'operator' => 'AND',
                'conditions' => [
                    ['field' => 'Task Board Type', 'operator' => 'equals', 'value' => 'Infrastructure'],
                    [
                        'operator' => 'AND',
                        'conditions' => [
                            ['field' => 'Status', 'operator' => 'equals', 'value' => 'Completed'],
                            ['field' => 'Completion Date', 'operator' => 'is_after', 'value' => '2024-12-31']
                        ]
                    ]
                ],
                'then_show' => ['Final Sign-off Form', 'As-Built Drawings', 'Client Acceptance Certificate'],
                'then_hide' => ['Sustainability Report', 'Risk Assessment']
            ],
            'created_by' => 'Workspace Governance Board',
            'last_modified_by' => 'Workspace Governance Board',
        ]);

        // Rule 9: Task Board Name Length Validation
        SystemRule::create([
            'name' => 'Task Board Name Length Validation',
            'type' => 'validation_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Form validation rule ensuring task board titles meet minimum length requirements of 5 characters during creation.',
            'scope' => ['Task Boards'],
            'actions' => ['show_error_message', 'reject_submission'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'field' => 'name',
                'validation_type' => 'min_length',
                'min_length' => 5,
                'error_message' => 'Task Board Name [Task Board Name] must be at least 5 characters long.',
            ],
            'created_by' => 'Workspace Governance Board',
            'last_modified_by' => 'Workspace Governance Board',
        ]);

        // Rule 10: Section Project Manager Task Allocation Authority
        SystemRule::create([
            'name' => 'Section Manager Task Allocation Authority',
            'type' => 'approval_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Workflow rule restricting task creation and workflow allocation to Section Project Managers managing the section or System Administrators.',
            'scope' => ['Tasks'],
            'actions' => ['enable_field', 'reject_submission'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'operation' => 'manage_tasks',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => ['project_manager'],
                'constraint' => 'section_manager_id_match',
            ],
            'created_by' => 'Workspace Governance Board',
            'last_modified_by' => 'Workspace Governance Board',
        ]);

        // Rule 11: Automated Overdue Task Escalation Alert
        SystemRule::create([
            'name' => 'Automated Overdue Task Escalation Alert',
            'type' => 'notification_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Escalation notification rule sending automated in-app and email alerts to Project Managers and Team Members when task due dates pass.',
            'scope' => ['Tasks'],
            'actions' => ['send_in_app_notification', 'send_email_notification'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'event' => 'task_overdue',
                'channels' => ['in_app', 'email'],
                'recipients' => ['Project Manager', 'Admins', 'Assigned Members']
            ],
            'created_by' => 'Workspace Governance Board',
            'last_modified_by' => 'Workspace Governance Board',
        ]);

        // Rule 12: Environmental & Risk Assessment Compliance Audit
        SystemRule::create([
            'name' => 'Environmental & Risk Assessment Compliance Audit',
            'type' => 'compliance_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Compliance audit rule enforcing regulatory environmental impact assessments and risk matrices for Sustainability task boards.',
            'scope' => ['Task Boards', 'Reports'],
            'actions' => ['log_critical_event'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'sector' => 'Environment',
                'standard' => 'ISO 14001',
                'requirements' => ['Environmental Impact Assessment', 'Risk Matrix']
            ],
            'created_by' => 'Workspace Governance Board',
            'last_modified_by' => 'Workspace Governance Board',
        ]);

        // Rule 13: Task Board Deletion Authority Restriction
        SystemRule::create([
            'name' => 'Task Board Deletion Authority Restriction',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide operational restriction reserving task board record deletion capabilities exclusively to System Administrators.',
            'scope' => ['Task Boards'],
            'actions' => ['hide_field', 'reject_submission'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'delete_project',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => [],
                'error_message' => 'Only System Administrators are authorized to delete task boards.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 14: Section Management Authority Rule
        SystemRule::create([
            'name' => 'Section Management Authority Rule',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide governance rule controlling section creation, modification, and deletion.',
            'scope' => ['Sections'],
            'actions' => ['reject_submission', 'show_error_message'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'manage_sections',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => [],
                'error_message' => 'Only System Administrators are authorized to manage organizational sections.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 15: Workflow & Development Phase Management Rule
        SystemRule::create([
            'name' => 'Workflow & Development Phase Management Rule',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide governance rule controlling workflow process structure and phase configuration.',
            'scope' => ['Workflows'],
            'actions' => ['reject_submission', 'show_error_message'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'manage_workflows',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => [],
                'error_message' => 'Only System Administrators are authorized to manage workflow phases.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 16: Functional Role Provisioning & Management Rule
        SystemRule::create([
            'name' => 'Functional Role Provisioning & Management Rule',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide governance rule controlling functional member role creation, editing, and assignment.',
            'scope' => ['Roles'],
            'actions' => ['reject_submission', 'show_error_message'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'manage_functional_roles',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => [],
                'error_message' => 'Only System Administrators are authorized to manage functional roles.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 17: Task Board Section Reassignment Authority Rule
        SystemRule::create([
            'name' => 'Task Board Section Reassignment Authority Rule',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Workspace restriction controlling who can change the section ownership of an existing task board.',
            'scope' => ['Task Boards', 'Sections'],
            'actions' => ['reject_submission', 'show_error_message'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'operation' => 'reassign_project_section',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => [],
                'error_message' => 'Only System Administrators can reassign a task board to a different section.',
            ],
            'created_by' => 'Workspace Governance Board',
            'last_modified_by' => 'Workspace Governance Board',
        ]);

        // Rule 18: Dashboard Task Boards Visibility Rule
        SystemRule::create([
            'name' => 'Dashboard Task Boards Visibility Rule',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide visibility rule allowing designated roles to view cross-sectional task board metrics on the dashboard.',
            'scope' => ['Dashboard'],
            'actions' => ['enable_field'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'view_all_projects_dashboard',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => [],
                'error_message' => 'You only have access to task boards in your assigned sections.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 21: Kanban & IPCR Workflow Protection Guard
        SystemRule::create([
            'name' => 'Kanban & IPCR Workflow Protection Guard',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide governance rule restricting modification and deletion of workflows and phases under Kanban and IPCR Workflow Types exclusively to System Administrators.',
            'scope' => ['Workflows'],
            'actions' => ['reject_submission', 'show_error_message'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'operation' => 'manage_protected_workflow_types',
                'protected_workflow_types' => ['Kanban', 'IPCR'],
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => [],
                'error_message' => 'Only System Administrators can update or delete items under Kanban & IPCR Workflow Types.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 22: Departments Access & Management Guard
        SystemRule::create([
            'name' => 'Departments Access & Management Guard',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide security rule restricting access to the Departments tab and Department CRUD management exclusively to System Administrators.',
            'scope' => ['Departments', 'Admin Management'],
            'actions' => ['reject_submission', 'show_error_message'],
            'rule_logic' => [
                'category' => 'system_wide_access',
                'operation' => 'manage_departments',
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => [],
                'error_message' => 'Only System Administrators can access and manage Departments.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);

        // Rule 23: Support Function Reports Edit Restriction Rule
        SystemRule::create([
            'name' => 'Support Function Reports Edit Restriction Rule',
            'type' => 'role_permission_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'System-wide security rule enforcing that only Members under Section "Admin" can edit and update tab data in Support Function Reports Page, while everyone else within the Department has view access.',
            'scope' => ['Support Function Reports'],
            'actions' => ['reject_submission', 'show_error_message'],
            'rule_logic' => [
                'category' => 'support_function_reports',
                'operation' => 'edit_support_function_reports',
                'allowed_sections' => ['Admin'],
                'bypass_system_roles' => ['admin'],
                'view_access_scope' => 'department',
                'error_message' => 'Only Members under Section "Admin" can edit and update report data in Support Function Reports.',
            ],
            'created_by' => 'System Security Architect',
            'last_modified_by' => 'System Security Architect',
        ]);
    }
}
