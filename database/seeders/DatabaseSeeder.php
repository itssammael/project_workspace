<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\RoleAccess;
use App\Models\MemberRole;
use App\Models\Member;
use App\Models\Workflow;
use App\Models\WorkflowType;
use App\Models\Section;
use App\Models\Project;
use App\Models\Task;
use App\Models\Setting;
use App\Models\SystemRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Seed Default Settings
        Setting::set('system_name', 'Project Tracker');
        Setting::set('theme', 'corporate_teal');
        Setting::set('logo', null);

        // 1. Create System Roles
        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $userRole = Role::create(['name' => 'User', 'slug' => 'user']);
        $viewerRole = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);

        // 2. Create Role Permissions (Role Access)
        RoleAccess::create(['role_id' => $adminRole->id, 'permission' => 'system.config']);
        RoleAccess::create(['role_id' => $adminRole->id, 'permission' => 'user.provision']);
        RoleAccess::create(['role_id' => $adminRole->id, 'permission' => 'view.dashboard']);

        RoleAccess::create(['role_id' => $userRole->id, 'permission' => 'view.dashboard']);
        RoleAccess::create(['role_id' => $userRole->id, 'permission' => 'edit.tasks']);

        RoleAccess::create(['role_id' => $viewerRole->id, 'permission' => 'view.dashboard']);

        // 3. Create Functional (Member) Roles
        $deptHead = MemberRole::create(['name' => 'Department Head', 'slug' => 'department_head']);
        $projManager = MemberRole::create(['name' => 'Project Manager', 'slug' => 'project_manager']);
        $uiUxDesigner = MemberRole::create(['name' => 'UI/UX Designer', 'slug' => 'ui_ux_designer']);
        $leadDeveloper = MemberRole::create(['name' => 'Lead Developer', 'slug' => 'lead_developer']);
        $developer = MemberRole::create(['name' => 'Developer', 'slug' => 'developer']);
        $adminStaff = MemberRole::create(['name' => 'Admin Staff', 'slug' => 'admin_staff']);

        // 4. Create Users & Members
        // Admin / Department Head
        $adminUser = User::create([
            'name' => 'Alex Administrator',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
        ]);
        $adminMember = Member::create([
            'user_id' => $adminUser->id,
        ]);
        $adminMember->memberRoles()->attach($deptHead->id);

        // Project Manager
        $pmUser = User::create([
            'name' => 'Paul Manager',
            'email' => 'manager@example.com',
            'username' => 'manager',
            'password' => Hash::make('password'),
            'role_id' => $userRole->id,
        ]);
        $pmMember = Member::create([
            'user_id' => $pmUser->id,
        ]);
        $pmMember->memberRoles()->attach($projManager->id);

        // Designer
        $designerUser = User::create([
            'name' => 'Diana Designer',
            'email' => 'designer@example.com',
            'username' => 'designer',
            'password' => Hash::make('password'),
            'role_id' => $userRole->id,
        ]);
        $designerMember = Member::create([
            'user_id' => $designerUser->id,
        ]);
        $designerMember->memberRoles()->attach($uiUxDesigner->id);

        // Developer
        $developerUser = User::create([
            'name' => 'Devon Developer',
            'email' => 'developer@example.com',
            'username' => 'developer',
            'password' => Hash::make('password'),
            'role_id' => $userRole->id,
        ]);
        $developerMember = Member::create([
            'user_id' => $developerUser->id,
        ]);
        $developerMember->memberRoles()->attach([$leadDeveloper->id, $developer->id]);

        // Viewer
        $viewerUser = User::create([
            'name' => 'Valerie Viewer',
            'email' => 'viewer@example.com',
            'username' => 'viewer',
            'password' => Hash::make('password'),
            'role_id' => $viewerRole->id,
        ]);
        $viewerMember = Member::create([
            'user_id' => $viewerUser->id,
        ]);

        // Admin Staff
        $staffUser = User::create([
            'name' => 'Sarah Staff',
            'email' => 'staff@example.com',
            'username' => 'staff',
            'password' => Hash::make('password'),
            'role_id' => $userRole->id,
        ]);
        $staffMember = Member::create([
            'user_id' => $staffUser->id,
        ]);
        $staffMember->memberRoles()->attach($adminStaff->id);

        // 5. Create Workflow Types
        $typeSoftware = WorkflowType::create(['name' => 'Software Development']);
        $typeAdmin = WorkflowType::create(['name' => 'Administrative and Operational Support']);
        $typeKanban = WorkflowType::create(['name' => 'Kanban']);

        // 6. Create Workflows
        $phaseReq = Workflow::create(['name' => 'Requirements', 'order' => 1, 'workflow_type_id' => $typeSoftware->id]);
        $phaseDesign = Workflow::create(['name' => 'Design', 'order' => 2, 'workflow_type_id' => $typeSoftware->id]);
        $phaseDev = Workflow::create(['name' => 'Development', 'order' => 3, 'workflow_type_id' => $typeSoftware->id]);
        $phaseTest = Workflow::create(['name' => 'Testing', 'order' => 4, 'workflow_type_id' => $typeSoftware->id]);
        $phaseDeploy = Workflow::create(['name' => 'Deployment', 'order' => 5, 'workflow_type_id' => $typeSoftware->id]);

        $kanbanTodo = Workflow::create(['name' => 'To do', 'order' => 0, 'workflow_type_id' => $typeKanban->id]);
        $kanbanDoing = Workflow::create(['name' => 'Doing', 'order' => 2, 'workflow_type_id' => $typeKanban->id]);
        $kanbanSubmitted = Workflow::create(['name' => 'Submitted', 'order' => 3, 'workflow_type_id' => $typeKanban->id]);
        $kanbanCompleted = Workflow::create(['name' => 'Completed', 'order' => 4, 'workflow_type_id' => $typeKanban->id]);

        // 7. Create Sections & Pivot bindings
        $sectionAlpha = Section::create([
            'name' => 'Alpha Software Section',
            'member_id' => $pmMember->id, // PM is the Manager
        ]);
        
        $sectionAlpha->members()->attach([
            $pmMember->id,
            $designerMember->id,
            $developerMember->id,
            $staffMember->id,
        ]);

        // 8. Create Projects
        $projectEcommerce = Project::create([
            'name' => 'E-Commerce Platform Redesign',
            'description' => 'Upgrade the existing store layout, migrate products database, and optimize checkout flows.',
            'status' => 'active',
            'section_id' => $sectionAlpha->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-28',
        ]);

        $projectEcommerce->workflows()->attach([
            $phaseReq->id,
            $phaseDesign->id,
            $phaseDev->id,
            $phaseTest->id,
            $phaseDeploy->id,
            $kanbanTodo->id,
            $kanbanDoing->id,
            $kanbanSubmitted->id,
            $kanbanCompleted->id,
        ]);

        $projectEcommerce->members()->attach([
            $pmMember->id => ['member_role_id' => $projManager->id],
            $designerMember->id => ['member_role_id' => $uiUxDesigner->id],
            $developerMember->id => ['member_role_id' => $leadDeveloper->id],
        ]);

        // 8. Create Tasks and Sub-tasks
        // Task 1: Requirements Analysis
        $t1 = Task::create([
            'name' => 'Define Scope & Requirements',
            'details' => 'Draft functional specification documents and list third-party APIs to integrate.',
            'workflow_id' => $phaseReq->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t1->subTasks()->create([
            'name' => 'Define Scope & Requirements Subtask',
            'details' => 'Draft functional specification documents and list third-party APIs to integrate.',
            'deliverables' => 'Functional Specs PDF, API Registry Spreadsheet',
            'duration' => 4,
            'member_id' => $pmMember->id,
            'start_date' => '2026-07-01',
            'status' => 'completed',
        ]);

        // Task 2: Database Schema
        $t2 = Task::create([
            'name' => 'Design Database Architecture',
            'details' => 'Draft relational schemas and plan performance optimization/indexes.',
            'workflow_id' => $phaseReq->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t2->subTasks()->create([
            'name' => 'Design Database Architecture Subtask',
            'details' => 'Draft relational schemas and plan performance optimization/indexes.',
            'deliverables' => 'DB Schema Diagram, Migration Scripts',
            'duration' => 3,
            'member_id' => $developerMember->id,
            'start_date' => '2026-07-05',
            'status' => 'completed',
        ]);

        // Task 3: UI Design
        $t3 = Task::create([
            'name' => 'Create High-Fidelity UI Mockups',
            'details' => 'Design interfaces for homepage, product detail page, and checkout process.',
            'workflow_id' => $phaseDesign->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t3->subTasks()->create([
            'name' => 'Create High-Fidelity UI Mockups Subtask',
            'details' => 'Design interfaces for homepage, product detail page, and checkout process.',
            'deliverables' => 'Figma Prototype Link',
            'duration' => 5,
            'member_id' => $designerMember->id,
            'start_date' => '2026-07-08',
            'status' => 'completed',
        ]);

        // Task 4: Frontend Development
        $t4 = Task::create([
            'name' => 'Frontend Assembly & Component Styling',
            'details' => 'Implement designs in Vue 3 with responsive layout structures.',
            'workflow_id' => $phaseDev->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t4->subTasks()->create([
            'name' => 'Frontend Assembly & Component Styling Subtask',
            'details' => 'Implement designs in Vue 3 with responsive layout structures.',
            'deliverables' => 'Vue files pushed to repository',
            'duration' => 6,
            'member_id' => $designerMember->id,
            'start_date' => '2026-07-13',
            'status' => 'in_progress',
        ]);

        // Task 5: Backend API Development
        $t5 = Task::create([
            'name' => 'Implement Backend Checkout API',
            'details' => 'Construct controller logic and integrate Stripe payment processing.',
            'workflow_id' => $phaseDev->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t5->subTasks()->create([
            'name' => 'Implement Backend Checkout API Subtask',
            'details' => 'Construct controller logic and integrate Stripe payment processing.',
            'deliverables' => 'Checkout endpoints, Stripe integration unit tests',
            'duration' => 8,
            'member_id' => $developerMember->id,
            'start_date' => '2026-07-13',
            'status' => 'in_progress',
        ]);

        // Task 6: Testing
        $t6 = Task::create([
            'name' => 'Perform Integration & QA Testing',
            'details' => 'Write end-to-end checkout flow automation tests and run security checks.',
            'workflow_id' => $phaseTest->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t6->subTasks()->create([
            'name' => 'Perform Integration & QA Testing Subtask',
            'details' => 'Write end-to-end checkout flow automation tests and run security checks.',
            'deliverables' => 'QA Checklist Report, Cypress Test Log',
            'duration' => 4,
            'member_id' => $developerMember->id,
            'start_date' => '2026-07-21',
            'status' => 'pending',
        ]);

        // Task 7: Client Deployment
        $t7 = Task::create([
            'name' => 'Staging & Production Deployment',
            'details' => 'Prepare environment configs and launch to production servers.',
            'workflow_id' => $phaseDeploy->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t7->subTasks()->create([
            'name' => 'Staging & Production Deployment Subtask',
            'details' => 'Prepare environment configs and launch to production servers.',
            'deliverables' => 'Live website access, Deployment log',
            'duration' => 2,
            'member_id' => $pmMember->id,
            'start_date' => '2026-07-25',
            'status' => 'pending',
        ]);

        // Task 8: Overdue Task (Undelivered)
        $t8 = Task::create([
            'name' => 'Final Brand Assets Package',
            'details' => 'Create SVG files for standard brand logo variations.',
            'workflow_id' => $phaseDesign->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t8->subTasks()->create([
            'name' => 'Final Brand Assets Package Subtask',
            'details' => 'Create SVG files for standard brand logo variations.',
            'deliverables' => 'Branding Assets ZIP',
            'duration' => 2,
            'member_id' => $designerMember->id,
            'start_date' => '2026-06-25', // Overdue since 2026-06-27 (relative to current date 2026-07-01)
            'status' => 'pending',
        ]);

        // Task 9: Setup Git Repository (Completed Kanban)
        $t9 = Task::create([
            'name' => 'Setup Git Repository',
            'details' => 'Initialize repo, setup main/dev branches, protect branches, and configure CI/CD starter templates.',
            'workflow_id' => $kanbanCompleted->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t9->subTasks()->create([
            'name' => 'Repository Setup Subtask',
            'details' => 'Initialize repo, setup main/dev branches, protect branches, and configure CI/CD starter templates.',
            'deliverables' => 'GitHub Repository Link, Branch Protection Rules',
            'duration' => 2,
            'member_id' => $developerMember->id,
            'start_date' => '2026-07-01',
            'status' => 'completed',
        ]);

        // Task 10: Configure Development Environment (Completed Kanban)
        $t10 = Task::create([
            'name' => 'Configure Dev Environment',
            'details' => 'Install packages, setup vite plugins, and verify local development server.',
            'workflow_id' => $kanbanCompleted->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t10->subTasks()->create([
            'name' => 'Environment Setup Subtask',
            'details' => 'Install packages, setup vite plugins, and verify local development server.',
            'deliverables' => 'Working local dev environments',
            'duration' => 1,
            'member_id' => $developerMember->id,
            'start_date' => '2026-07-03',
            'status' => 'completed',
        ]);

        // Task 11: Draft Project Roadmap (Doing Kanban)
        $t11 = Task::create([
            'name' => 'Draft Project Roadmap',
            'details' => 'List timeline phases, identify milestones, and draft subtask plans.',
            'workflow_id' => $kanbanDoing->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t11->subTasks()->create([
            'name' => 'Roadmap Drafting Subtask',
            'details' => 'List timeline phases, identify milestones, and draft subtask plans.',
            'deliverables' => 'Gantt Chart Setup Draft',
            'duration' => 4,
            'member_id' => $pmMember->id,
            'start_date' => '2026-07-14',
            'status' => 'in_progress',
        ]);

        // Task 12: Review Codebase Standards (To do Kanban)
        $t12 = Task::create([
            'name' => 'Review Codebase Standards',
            'details' => 'Define styling rules, directory structure guidelines, and write template scripts.',
            'workflow_id' => $kanbanTodo->id,
            'project_id' => $projectEcommerce->id,
        ]);
        $t12->subTasks()->create([
            'name' => 'Standards Drafting Subtask',
            'details' => 'Define styling rules, directory structure guidelines, and write template scripts.',
            'deliverables' => 'Style Guide MD File',
            'duration' => 2,
            'member_id' => $developerMember->id,
            'start_date' => '2026-07-16',
            'status' => 'pending',
        ]);

        // 8. Seed System Rules
        $this->call(SystemRuleSeeder::class);
    }
}
