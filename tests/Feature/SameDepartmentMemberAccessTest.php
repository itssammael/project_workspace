<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Section;
use App\Models\Department;
use App\Models\SystemRule;
use App\Services\SystemRuleEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SameDepartmentMemberAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_same_department_member_scoping(): void
    {
        // Ensure SystemRule for same_department_member_access exists and is enabled
        $rule = SystemRule::updateOrCreate(
            ['name' => 'Same-Department Member Access & Visibility Guard'],
            [
                'type' => 'role_permission_rule',
                'enabled' => true,
                'status' => 'active',
                'description' => 'Same-department member isolation test rule',
                'scope' => ['Users', 'Sections', 'Departments'],
                'actions' => ['filter_data_scope'],
                'rule_logic' => [
                    'category' => 'system_wide_access',
                    'operation' => 'same_department_member_access',
                    'enforce_same_department' => true,
                    'bypass_system_roles' => ['admin'],
                ],
            ]
        );

        $admin = User::where('email', 'admin@example.com')->first();
        $staff = User::where('email', 'staff@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($staff);

        // System Administrator gets all members
        $adminScopedMembers = SystemRuleEvaluator::scopeMemberQueryByDepartmentRule(Member::query(), $admin)->get();
        $this->assertEquals(Member::count(), $adminScopedMembers->count());

        // Regular staff only gets members in their department
        $staffDeptIds = SystemRuleEvaluator::getUserDepartmentIds($staff);
        $staffScopedMembers = SystemRuleEvaluator::scopeMemberQueryByDepartmentRule(Member::query(), $staff)->get();

        foreach ($staffScopedMembers as $m) {
            $mUser = $m->user;
            if ($mUser) {
                $targetDeptIds = SystemRuleEvaluator::getUserDepartmentIds($mUser);
                $this->assertNotEmpty(array_intersect($staffDeptIds, $targetDeptIds));
            }
        }
    }
}
