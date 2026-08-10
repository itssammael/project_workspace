<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmployeeType;

class EmployeeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employeeTypes = [
            ['id' => 1, 'description' => 'Regular'],
            ['id' => 2, 'description' => 'Casual'],
            ['id' => 3, 'description' => 'JOW'],
        ];

        foreach ($employeeTypes as $type) {
            EmployeeType::updateOrCreate(['id' => $type['id']], $type);
        }
    }
}
