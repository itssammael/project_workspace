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
        if (Schema::hasTable('workflows')) {
            Schema::table('workflows', function (Blueprint $table) {
                if (!Schema::hasColumn('workflows', 'workflow_type_id')) {
                    $table->foreignId('workflow_type_id')
                          ->nullable()
                          ->after('order')
                          ->constrained('workflow_types')
                          ->nullOnDelete();
                }
            });

            Schema::table('workflows', function (Blueprint $table) {
                if (Schema::hasColumn('workflows', 'project_type')) {
                    $table->dropColumn('project_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('workflows')) {
            Schema::table('workflows', function (Blueprint $table) {
                if (!Schema::hasColumn('workflows', 'project_type')) {
                    $table->string('project_type')->nullable()->after('name');
                }
            });

            Schema::table('workflows', function (Blueprint $table) {
                if (Schema::hasColumn('workflows', 'workflow_type_id')) {
                    $table->dropForeign(['workflow_type_id']);
                    $table->dropColumn('workflow_type_id');
                }
            });
        }
    }
};

