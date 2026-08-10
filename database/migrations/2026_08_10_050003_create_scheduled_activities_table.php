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
        Schema::create('scheduled_activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('activity_type_id')->constrained('activity_types')->onDelete('cascade');
            $table->date('date');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->integer('year');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_activities');
    }
};
