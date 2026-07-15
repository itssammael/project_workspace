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
        // 1. Drop foreign key constraints referencing the old tables/columns
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        Schema::table('teams_member_pivot', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        // 2. Rename tables
        Schema::rename('teams', 'sections');
        Schema::rename('teams_member_pivot', 'sections_member_pivot');

        // 3. Rename columns
        Schema::table('sections_member_pivot', function (Blueprint $table) {
            $table->renameColumn('team_id', 'section_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('team_id', 'section_id');
        });

        // 4. Re-add foreign key constraints
        Schema::table('sections_member_pivot', function (Blueprint $table) {
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop new foreign keys
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
        });

        Schema::table('sections_member_pivot', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
        });

        // 2. Rename columns back
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('section_id', 'team_id');
        });

        Schema::table('sections_member_pivot', function (Blueprint $table) {
            $table->renameColumn('section_id', 'team_id');
        });

        // 3. Rename tables back
        Schema::rename('sections_member_pivot', 'teams_member_pivot');
        Schema::rename('sections', 'teams');

        // 4. Re-add old foreign keys
        Schema::table('teams_member_pivot', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });
    }
};
