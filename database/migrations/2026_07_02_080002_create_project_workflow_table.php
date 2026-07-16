<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_workflow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Seed pivot bindings for existing projects
        $projects = DB::table('projects')->get();
        $workflows = DB::table('workflows')->get();
        $pivotData = [];

        foreach ($projects as $project) {
            foreach ($workflows as $workflow) {
                $pivotData[] = [
                    'project_id' => $project->id,
                    'workflow_id' => $workflow->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($pivotData)) {
            DB::table('project_workflow')->insert($pivotData);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_workflow');
    }
};
