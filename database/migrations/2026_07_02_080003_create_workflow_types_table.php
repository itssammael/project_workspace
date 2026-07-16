<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create workflow_types table
        if (!Schema::hasTable('workflow_types')) {
            Schema::create('workflow_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        // Determine target table (either workflows or development_phases)
        $targetTable = Schema::hasTable('workflows') ? 'workflows' : 'development_phases';

        // 2. Add workflow_type_id to workflows/development_phases table
        if (Schema::hasTable($targetTable) && !Schema::hasColumn($targetTable, 'workflow_type_id')) {
            Schema::table($targetTable, function (Blueprint $table) {
                $table->foreignId('workflow_type_id')
                      ->nullable()
                      ->after('order')
                      ->constrained('workflow_types')
                      ->nullOnDelete();
            });
        }

        // 3. Migrate existing project_type string data to the new workflow_types table
        if (Schema::hasTable($targetTable) && Schema::hasColumn($targetTable, 'project_type')) {
            $workflows = DB::table($targetTable)->get();
            $typesCreated = [];

            foreach ($workflows as $workflow) {
                $projectType = $workflow->project_type;
                if ($projectType) {
                    $typeName = trim($projectType);
                    if ($typeName !== '') {
                        if (!isset($typesCreated[$typeName])) {
                            $typeId = DB::table('workflow_types')->insertGetId([
                                'name' => $typeName,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $typesCreated[$typeName] = $typeId;
                        }
                        
                        DB::table($targetTable)
                            ->where('id', $workflow->id)
                            ->update(['workflow_type_id' => $typesCreated[$typeName]]);
                    }
                }
            }

            // 4. Drop the obsolete project_type column
            Schema::table($targetTable, function (Blueprint $table) {
                $table->dropColumn('project_type');
            });
        }
    }

    public function down(): void
    {
        $targetTable = Schema::hasTable('workflows') ? 'workflows' : 'development_phases';

        if (Schema::hasTable($targetTable)) {
            // 1. Add back project_type column if it was removed
            if (!Schema::hasColumn($targetTable, 'project_type')) {
                Schema::table($targetTable, function (Blueprint $table) {
                    $table->string('project_type')->nullable()->after('name');
                });
            }

            // 2. Rollback relationship values
            if (Schema::hasColumn($targetTable, 'workflow_type_id')) {
                $workflows = DB::table($targetTable)->get();
                foreach ($workflows as $workflow) {
                    if ($workflow->workflow_type_id) {
                        $type = DB::table('workflow_types')->where('id', $workflow->workflow_type_id)->first();
                        if ($type) {
                            DB::table($targetTable)
                                ->where('id', $workflow->id)
                                ->update(['project_type' => $type->name]);
                        }
                    }
                }

                // 3. Drop foreign key and column
                Schema::table($targetTable, function (Blueprint $table) {
                    $table->dropForeign(['workflow_type_id']);
                    $table->dropColumn('workflow_type_id');
                });
            }
        }

        // 4. Drop workflow_types table
        Schema::dropIfExists('workflow_types');
    }
};
