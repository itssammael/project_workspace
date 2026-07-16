<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('details')->nullable();
            $table->text('deliverables')->nullable();
            $table->integer('duration'); // duration in days
            $table->foreignId('workflow_id')->constrained('workflows')->onDelete('cascade');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->date('start_date')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
