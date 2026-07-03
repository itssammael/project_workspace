<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_development_phase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('development_phase_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Seed pivot bindings for existing projects
        $projects = DB::table('projects')->get();
        $phases = DB::table('development_phases')->get();
        $pivotData = [];

        foreach ($projects as $project) {
            foreach ($phases as $phase) {
                $pivotData[] = [
                    'project_id' => $project->id,
                    'development_phase_id' => $phase->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($pivotData)) {
            DB::table('project_development_phase')->insert($pivotData);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_development_phase');
    }
};
