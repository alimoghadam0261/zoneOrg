<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zone_access_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();

            // person → target_id = people.id, department → target_id = NULL + target_value,
            // contract_type → target_id = NULL + target_value
            $table->enum('target_type', ['person', 'department', 'contract_type']);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_value')->nullable();

            $table->enum('access_type', ['allow', 'deny'])->default('deny');

            // null = always valid (otherwise a datetime window)
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();

            $table->timestamps();

            $table->index(['zone_id', 'target_type']);
            $table->index('target_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_access_rules');
    }
};
