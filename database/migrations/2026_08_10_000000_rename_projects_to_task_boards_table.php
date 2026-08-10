<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop foreign keys referencing old tables/columns
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        }

        if (Schema::hasTable('project_workflow')) {
            Schema::table('project_workflow', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        }

        if (Schema::hasTable('project_members')) {
            Schema::table('project_members', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        }

        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropForeign(['section_id']);
            });
        }

        // 2. Rename tables
        Schema::rename('projects', 'task_boards');
        Schema::rename('project_workflow', 'task_board_workflow');
        Schema::rename('project_members', 'task_board_members');

        // 3. Rename columns
        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('project_id', 'task_board_id');
        });

        Schema::table('task_board_workflow', function (Blueprint $table) {
            $table->renameColumn('project_id', 'task_board_id');
        });

        Schema::table('task_board_members', function (Blueprint $table) {
            $table->renameColumn('project_id', 'task_board_id');
        });

        // 4. Re-add foreign key constraints
        Schema::table('task_boards', function (Blueprint $table) {
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('set null');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('task_board_id')->references('id')->on('task_boards')->onDelete('cascade');
        });

        Schema::table('task_board_workflow', function (Blueprint $table) {
            $table->foreign('task_board_id')->references('id')->on('task_boards')->onDelete('cascade');
        });

        Schema::table('task_board_members', function (Blueprint $table) {
            $table->foreign('task_board_id')->references('id')->on('task_boards')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop new foreign keys
        Schema::table('task_board_members', function (Blueprint $table) {
            $table->dropForeign(['task_board_id']);
        });

        Schema::table('task_board_workflow', function (Blueprint $table) {
            $table->dropForeign(['task_board_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['task_board_id']);
        });

        Schema::table('task_boards', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
        });

        // 2. Rename columns back
        Schema::table('task_board_members', function (Blueprint $table) {
            $table->renameColumn('task_board_id', 'project_id');
        });

        Schema::table('task_board_workflow', function (Blueprint $table) {
            $table->renameColumn('task_board_id', 'project_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('task_board_id', 'project_id');
        });

        // 3. Rename tables back
        Schema::rename('task_board_members', 'project_members');
        Schema::rename('task_board_workflow', 'project_workflow');
        Schema::rename('task_boards', 'projects');

        // 4. Re-add old foreign keys
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('set null');
        });

        Schema::table('project_members', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::table('project_workflow', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }
};
