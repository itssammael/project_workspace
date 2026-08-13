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
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('status')->nullable()->after('is_present');
        });

        Schema::table('tardiness_absences_undertimes', function (Blueprint $table) {
            $table->string('tardy')->default('0')->change();
            $table->string('absences')->default('0')->change();
            $table->string('undertime')->default('0')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('tardiness_absences_undertimes', function (Blueprint $table) {
            $table->integer('tardy')->default(0)->change();
            $table->integer('absences')->default(0)->change();
            $table->integer('undertime')->default(0)->change();
        });
    }
};
