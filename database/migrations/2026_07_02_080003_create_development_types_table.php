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
        // 1. Create development_types table
        Schema::create('development_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 2. Add development_type_id to development_phases table
        Schema::table('development_phases', function (Blueprint $table) {
            $table->foreignId('development_type_id')
                  ->nullable()
                  ->after('project_type')
                  ->constrained('development_types')
                  ->nullOnDelete();
        });

        // 3. Migrate existing project_type string data to the new development_types table
        $phases = DB::table('development_phases')->get();
        $typesCreated = [];

        foreach ($phases as $phase) {
            $projectType = $phase->project_type;
            if ($projectType) {
                // Determine normalized name (e.g. "Software" -> "Software")
                $typeName = trim($projectType);
                if ($typeName !== '') {
                    if (!isset($typesCreated[$typeName])) {
                        $typeId = DB::table('development_types')->insertGetId([
                            'name' => $typeName,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $typesCreated[$typeName] = $typeId;
                    }
                    
                    DB::table('development_phases')
                        ->where('id', $phase->id)
                        ->update(['development_type_id' => $typesCreated[$typeName]]);
                }
            }
        }

        // 4. Drop the obsolete project_type column
        Schema::table('development_phases', function (Blueprint $table) {
            $table->dropColumn('project_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add back project_type column
        Schema::table('development_phases', function (Blueprint $table) {
            $table->string('project_type')->nullable()->after('name');
        });

        // 2. Rollback relationship values
        $phases = DB::table('development_phases')->get();
        foreach ($phases as $phase) {
            if ($phase->development_type_id) {
                $type = DB::table('development_types')->where('id', $phase->development_type_id)->first();
                if ($type) {
                    DB::table('development_phases')
                        ->where('id', $phase->id)
                        ->update(['project_type' => $type->name]);
                }
            }
        }

        // 3. Drop foreign key and column
        Schema::table('development_phases', function (Blueprint $table) {
            $table->dropForeign(['development_type_id']);
            $table->dropColumn('development_type_id');
        });

        // 4. Drop development_types table
        Schema::dropIfExists('development_types');
    }
};
