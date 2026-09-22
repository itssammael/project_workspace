<?php

namespace Tests\Feature;

use App\Models\EmployeeType;
use App\Models\MemberRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SectionMembersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionMembersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_jow_members_are_seeded_correctly(): void
    {
        $this->seed(SectionMembersSeeder::class);

        $jowType = EmployeeType::where('description', 'JOW')->first();
        $this->assertNotNull($jowType);

        $userRole = Role::where('slug', 'user')->first();
        $this->assertNotNull($userRole);

        $adminStaffRole = MemberRole::where('slug', 'admin_staff')->first();
        $this->assertNotNull($adminStaffRole);

        $expectedMembers = [
            'u00000022' => ['name' => 'REGINO, IRENE A.', 'section' => 'Admin'],
            'u00000023' => ['name' => 'JORDAN PHILLIP REJAN T.', 'section' => 'Admin'],
            'u00000024' => ['name' => 'LIRAZAN, MARIA RITA M.', 'section' => 'Admin'],
            'u00000025' => ['name' => 'RENDON, ROJEAN V.', 'section' => 'Software'],
            'u00000026' => ['name' => 'PALLORINA, SEANNA MARIE B.', 'section' => 'Customer Care'],
            'u00000027' => ['name' => 'TUMALE, LOVEPRIT M.', 'section' => 'Customer Care'],
            'u00000028' => ['name' => 'PUYAT, DOROTHY MAY', 'section' => 'Customer Care'],
            'u00000029' => ['name' => 'TABAÑAG, JELAICA B.', 'section' => 'Customer Care'],
            'u00000030' => ['name' => 'INTAS, KIMBERLY C.', 'section' => 'Customer Care'],
            'u00000031' => ['name' => 'TADENA, WILLIAM JORDAN B.', 'section' => 'Customer Care'],
            'u00000032' => ['name' => 'BERONIO, MARLOU P.', 'section' => 'Network'],
            'u00000033' => ['name' => 'ALEGRIA, EARL C.', 'section' => 'Network'],
            'u00000034' => ['name' => 'BUENAFLOR, CARYL A.', 'section' => 'GIS'],
            'u00000035' => ['name' => 'MIJARES, JUREN CARL M.', 'section' => 'GIS'],
            'u00000036' => ['name' => 'BANGAY, JOSE ANTONIO', 'section' => 'GIS'],
            'u00000037' => ['name' => 'LINDAYAO, JAY', 'section' => 'Hardware'],
            'u00000038' => ['name' => 'YONG, RENO', 'section' => 'Hardware'],
            'u00000039' => ['name' => 'CORDOVA, JUSTINE', 'section' => 'Hardware'],
            'u00000040' => ['name' => 'ILAGAN , DARRYL', 'section' => 'Hardware'],
            'u00000042' => ['name' => 'PAEL, RENAN COLLIN', 'section' => 'LFEWS'],
            'u00000043' => ['name' => 'OLORES, RASSHEM', 'section' => 'LFEWS'],
            'u00000044' => ['name' => 'EBOGON, ANGELITO', 'section' => 'LFEWS'],
            'u00000045' => ['name' => 'PACAÑA, BRAYN', 'section' => 'LFEWS'],
            'u00000046' => ['name' => 'TAROY, JOVEL', 'section' => 'LFEWS'],
        ];

        foreach ($expectedMembers as $username => $data) {
            $user = User::where('username', $username)->first();
            $this->assertNotNull($user, "User {$username} should exist.");
            $this->assertEquals($data['name'], $user->name);
            $this->assertEquals($userRole->id, $user->role_id, "User {$username} system role should be User.");

            $member = $user->member;
            $this->assertNotNull($member, "User {$username} should have a member record.");
            $this->assertEquals($jowType->id, $member->employee_type_id, "User {$username} employee type should be JOW.");

            $this->assertTrue(
                $member->memberRoles->contains($adminStaffRole->id),
                "User {$username} should have Admin Staff functional role."
            );

            $this->assertTrue(
                $member->sections->pluck('name')->contains($data['section']),
                "User {$username} should be assigned to section {$data['section']}."
            );
        }
    }
}
