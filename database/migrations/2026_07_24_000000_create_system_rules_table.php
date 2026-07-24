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
        Schema::create('system_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // conditional_logic, validation_rule, approval_rule, notification_rule, compliance_rule, data_integrity_rule
            $table->boolean('enabled')->default(true);
            $table->string('status')->default('active'); // active, inactive, draft
            $table->text('description')->nullable();
            $table->json('rule_logic')->nullable();
            $table->json('scope')->nullable();
            $table->json('actions')->nullable();
            $table->string('created_by')->nullable();
            $table->string('last_modified_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_rules');
    }
};
