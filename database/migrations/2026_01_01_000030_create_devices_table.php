<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_uid', 64)->unique();
            $table->enum('type', ['mobile_app', 'gps_tag'])->default('gps_tag');
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->unsignedTinyInteger('battery_level')->default(100);
            $table->timestamp('last_seen_at')->nullable();
            // sha256 of the plaintext Sanctum bearer token (never stored in clear text)
            $table->string('api_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->index('person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
