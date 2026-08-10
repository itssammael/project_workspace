<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\MemberRole;
use App\Models\Member;
use App\Models\Section;
use App\Models\EmployeeType;
use Illuminate\Support\Facades\Hash;

class SectionMembersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userRole = Role::where('slug', 'user')->first() ?? Role::create(['name' => 'User', 'slug' => 'user']);
        $adminStaffRole = MemberRole::where('slug', 'admin_staff')->first() ?? MemberRole::create(['name' => 'Admin Staff', 'slug' => 'admin_staff']);
        $regularEmployeeType = EmployeeType::where('description', 'Regular')->first();
        $employeeTypeId = $regularEmployeeType ? $regularEmployeeType->id : 1;

        $sectionData = [
            'Admin' => [
                'ALICANTE, S.',
                'LAMPAGO, RM.',
            ],
            'Software' => [
                'ELNAR, MC.',
                'PINANONANG, R.',
                'ZERNA, E.',
                'AGUILAR, L W.',
                'NAMA, M.',
                'ENCABO, D.',
                'MARINDA, L.',
                'ARNAIZ, EG.',
            ],
            'Network' => [
                'PINILI, A.',
                'MENDIOLA, NR.',
            ],
            'GIS' => [
                'ENTAC, ML.',
            ],
            'Hardware' => [
                'ARQUIO, JR.',
                'ALCAZAR, N.',
                'CALDA, J.',
            ],
            'Customer Care' => [
                'BELINGAN, K.',
                'AMORES, MC.',
            ],
            'LFEWS' => [
                'BOLLOS, LH.',
                'TABUNDA, AP.',
            ],
        ];

        $counter = 1;

        $department = \App\Models\Department::find(2) ?? \App\Models\Department::first() ?? \App\Models\Department::create(['name' => 'Information Technology', 'short_name' => 'IT']);
        $deptId = $department->id;

        foreach ($sectionData as $sectionName => $names) {
            $section = Section::firstOrCreate(
                ['name' => $sectionName],
                ['department_id' => $deptId]
            );

            foreach ($names as $name) {
                $username = sprintf('u%08d', $counter);
                $email = "{$username}@bayawancity.gov.ph";

                $user = User::updateOrCreate(
                    ['username' => $username],
                    [
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make('password'),
                        'role_id' => $userRole->id,
                    ]
                );

                $member = Member::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'employee_type_id' => $employeeTypeId,
                    ]
                );

                $member->memberRoles()->syncWithoutDetaching([$adminStaffRole->id]);
                $section->members()->syncWithoutDetaching([$member->id]);

                $counter++;
            }
        }
    }
}
