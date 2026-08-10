<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Department::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'default',
                'short_name' => 'def',
                'department_head_id' => 1,
            ]
        );

        Department::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Information Technology',
                'short_name' => 'IT',
                'department_head_id' => 1,
            ]
        );
    }
}
