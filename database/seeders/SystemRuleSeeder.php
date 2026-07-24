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

        // =========================================================
        // DOMAIN 2: WORKSPACE & FORM GOVERNANCE RULES
        // =========================================================

        // Rule 7: Project Creation Authority
        SystemRule::create([
            'name' => 'Project Creation Authority Rule',
            'type' => 'approval_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Workspace governance rule restricting project creation capabilities to System Administrators and Department Heads.',
            'scope' => ['Projects'],
            'actions' => ['route_for_approval', 'reject_submission'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'operation' => 'create_project',
                'approval_chain' => ['Department Head', 'Administrator'],
                'allowed_system_roles' => ['admin'],
                'allowed_functional_roles' => ['department_head'],
                'error_message' => 'Only Department Heads and Administrators are authorized to create new projects.',
            ],
            'created_by' => 'Workspace Governance Board',
            'last_modified_by' => 'Workspace Governance Board',
        ]);

        // Rule 8: Mandatory Client Feedback & Sign-off
        SystemRule::create([
            'name' => 'Infrastructure Project Sign-off Documentation',
            'type' => 'conditional_logic',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Conditional logic rule mandating that all completed Infrastructure projects display required sign-off form and drawing attachments.',
            'scope' => ['Projects'],
            'actions' => ['show_field', 'hide_field'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'operator' => 'AND',
                'conditions' => [
                    ['field' => 'Project Type', 'operator' => 'equals', 'value' => 'Infrastructure'],
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

        // Rule 9: Project Name Length Validation
        SystemRule::create([
            'name' => 'Project Name Length Validation',
            'type' => 'validation_rule',
            'enabled' => true,
            'status' => 'active',
            'description' => 'Form validation rule ensuring project titles meet minimum length requirements of 5 characters during project creation.',
            'scope' => ['Projects'],
            'actions' => ['show_error_message', 'reject_submission'],
            'rule_logic' => [
                'category' => 'workspace_governance',
                'field' => 'name',
                'validation_type' => 'min_length',
                'min_length' => 5,
                'error_message' => 'Project Name [Project Name] must be at least 5 characters long.',
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
            'description' => 'Compliance audit rule enforcing regulatory environmental impact assessments and risk matrices for Sustainability projects.',
            'scope' => ['Projects', 'Reports'],
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
    }
}
