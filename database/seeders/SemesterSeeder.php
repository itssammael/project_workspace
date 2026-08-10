<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Semester;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Semester::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'First Semester',
                'month_start' => 1,
                'month_end' => 6,
            ]
        );

        Semester::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Second Semester',
                'month_start' => 7,
                'month_end' => 12,
            ]
        );
    }
}
