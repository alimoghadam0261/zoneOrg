<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('personnel_code')->unique();
            $table->string('full_name');
            $table->string('national_id', 10)->nullable()->unique();
            $table->string('department')->nullable()->index();
            $table->enum('contract_type', ['employee', 'contractor', 'visitor'])->default('employee');
            $table->string('avatar_url')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
