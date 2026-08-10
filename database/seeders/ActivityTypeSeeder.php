<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityType;

class ActivityTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ActivityType::updateOrCreate(
            ['id' => 1],
            ['name' => 'MMP']
        );

        ActivityType::updateOrCreate(
            ['id' => 2],
            ['name' => 'LGU Activities']
        );
    }
}
