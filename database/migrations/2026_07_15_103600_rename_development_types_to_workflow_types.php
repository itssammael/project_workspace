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
        // 1. Drop foreign key constraint referencing the old table
        Schema::table('development_phases', function (Blueprint $table) {
            $table->dropForeign(['development_type_id']);
        });

        // 2. Rename table
        Schema::rename('development_types', 'workflow_types');

        // 3. Rename column
        Schema::table('development_phases', function (Blueprint $table) {
            $table->renameColumn('development_type_id', 'workflow_type_id');
        });

        // 4. Re-add foreign key constraint
        Schema::table('development_phases', function (Blueprint $table) {
            $table->foreign('workflow_type_id')->references('id')->on('workflow_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop new foreign key constraint
        Schema::table('development_phases', function (Blueprint $table) {
            $table->dropForeign(['workflow_type_id']);
        });

        // 2. Rename column back
        Schema::table('development_phases', function (Blueprint $table) {
            $table->renameColumn('workflow_type_id', 'development_type_id');
        });

        // 3. Rename table back
        Schema::rename('workflow_types', 'development_types');

        // 4. Re-add old foreign key constraint
        Schema::table('development_phases', function (Blueprint $table) {
            $table->foreign('development_type_id')->references('id')->on('development_types')->onDelete('set null');
        });
    }
};
