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
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropColumn(['deliverables', 'duration', 'member_id', 'start_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('deliverables')->nullable();
            $table->integer('duration')->nullable();
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->date('start_date')->nullable();
            $table->string('status')->default('pending');
        });
    }
};
