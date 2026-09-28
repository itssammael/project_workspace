<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('sub_tasks', 'priority')) {
                $table->string('priority')->default('medium')->after('duration');
            }
            if (!Schema::hasColumn('sub_tasks', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('status');
            }
            if (!Schema::hasColumn('sub_tasks', 'blocked_reason')) {
                $table->string('blocked_reason')->nullable()->after('is_blocked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sub_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('sub_tasks', 'blocked_reason')) {
                $table->dropColumn('blocked_reason');
            }
            if (Schema::hasColumn('sub_tasks', 'is_blocked')) {
                $table->dropColumn('is_blocked');
            }
            if (Schema::hasColumn('sub_tasks', 'priority')) {
                $table->dropColumn('priority');
            }
        });
    }
};
