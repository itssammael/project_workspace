<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_types', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->timestamps();
        });

        // Insert default employee types so foreign key default value 1 resolves cleanly for existing rows
        DB::table('employee_types')->insert([
            ['id' => 1, 'description' => 'Regular', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'description' => 'Casual', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'description' => 'JOW', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('employee_type_id')
                ->default(1)
                ->after('user_id')
                ->constrained('employee_types')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['employee_type_id']);
            $table->dropColumn('employee_type_id');
        });

        Schema::dropIfExists('employee_types');
    }
};
