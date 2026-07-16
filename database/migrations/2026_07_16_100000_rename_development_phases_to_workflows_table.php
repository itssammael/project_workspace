<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. If both development_phases and workflows tables exist, and workflows is empty, drop workflows
        if (Schema::hasTable('development_phases') && Schema::hasTable('workflows')) {
            $workflowsCount = DB::table('workflows')->count();
            if ($workflowsCount === 0) {
                Schema::dropIfExists('workflows');
            }
        }

        // 2. Rename development_phases to workflows if development_phases exists
        if (Schema::hasTable('development_phases') && !Schema::hasTable('workflows')) {
            Schema::rename('development_phases', 'workflows');
        }

        // 3. If both project_development_phase and project_workflow exist, and project_workflow is empty, drop project_workflow
        if (Schema::hasTable('project_development_phase') && Schema::hasTable('project_workflow')) {
            $projectWorkflowCount = DB::table('project_workflow')->count();
            if ($projectWorkflowCount === 0) {
                Schema::dropIfExists('project_workflow');
            }
        }

        // 4. Rename project_development_phase to project_workflow if project_development_phase exists
        if (Schema::hasTable('project_development_phase') && !Schema::hasTable('project_workflow')) {
            Schema::rename('project_development_phase', 'project_workflow');
        }

        // 5. In tasks table: Rename development_phase_id to workflow_id and update foreign key constraint
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'development_phase_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                try {
                    $table->dropForeign(['development_phase_id']);
                } catch (\Exception $e) {
                    // Fallback to name if generic array dropping fails
                    try {
                        $table->dropForeign('tasks_development_phase_id_foreign');
                    } catch (\Exception $ex) {}
                }
                $table->renameColumn('development_phase_id', 'workflow_id');
            });

            Schema::table('tasks', function (Blueprint $table) {
                $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            });
        }

        // 6. In project_workflow table: Rename development_phase_id to workflow_id and update foreign key constraint
        if (Schema::hasTable('project_workflow') && Schema::hasColumn('project_workflow', 'development_phase_id')) {
            Schema::table('project_workflow', function (Blueprint $table) {
                try {
                    $table->dropForeign(['development_phase_id']);
                } catch (\Exception $e) {
                    try {
                        $table->dropForeign('project_development_phase_development_phase_id_foreign');
                    } catch (\Exception $ex) {}
                }
                $table->renameColumn('development_phase_id', 'workflow_id');
            });

            Schema::table('project_workflow', function (Blueprint $table) {
                $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            });
        }

        // 7. Update foreign key of workflow_type_id in workflows table
        if (Schema::hasTable('workflows') && Schema::hasColumn('workflows', 'workflow_type_id')) {
            Schema::table('workflows', function (Blueprint $table) {
                try {
                    $table->dropForeign(['workflow_type_id']);
                } catch (\Exception $e) {
                    try {
                        $table->dropForeign('development_phases_workflow_type_id_foreign');
                    } catch (\Exception $ex) {}
                }
            });

            Schema::table('workflows', function (Blueprint $table) {
                $table->foreign('workflow_type_id')->references('id')->on('workflow_types')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        // 1. In workflows table: revert foreign key
        if (Schema::hasTable('workflows') && Schema::hasColumn('workflows', 'workflow_type_id')) {
            Schema::table('workflows', function (Blueprint $table) {
                try {
                    $table->dropForeign(['workflow_type_id']);
                } catch (\Exception $e) {}
            });

            Schema::table('workflows', function (Blueprint $table) {
                $table->foreign('workflow_type_id')->references('id')->on('workflow_types')->onDelete('set null');
            });
        }

        // 2. In project_workflow table: Revert column name and constraint
        if (Schema::hasTable('project_workflow') && Schema::hasColumn('project_workflow', 'workflow_id')) {
            Schema::table('project_workflow', function (Blueprint $table) {
                try {
                    $table->dropForeign(['workflow_id']);
                } catch (\Exception $e) {}
                $table->renameColumn('workflow_id', 'development_phase_id');
            });

            Schema::table('project_workflow', function (Blueprint $table) {
                $table->foreign('development_phase_id')->references('id')->on('workflows')->onDelete('cascade');
            });
        }

        // 3. Rename project_workflow back to project_development_phase
        if (Schema::hasTable('project_workflow') && !Schema::hasTable('project_development_phase')) {
            Schema::rename('project_workflow', 'project_development_phase');
        }

        // 4. In tasks table: Revert column name and constraint
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'workflow_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                try {
                    $table->dropForeign(['workflow_id']);
                } catch (\Exception $e) {}
                $table->renameColumn('workflow_id', 'development_phase_id');
            });

            Schema::table('tasks', function (Blueprint $table) {
                $table->foreign('development_phase_id')->references('id')->on('workflows')->onDelete('cascade');
            });
        }

        // 5. Rename workflows back to development_phases
        if (Schema::hasTable('workflows') && !Schema::hasTable('development_phases')) {
            Schema::rename('workflows', 'development_phases');
        }
    }
};
