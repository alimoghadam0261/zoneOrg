<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_pings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            $table->float('accuracy');
            $table->float('speed')->nullable();
            $table->float('heading')->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('created_at')->nullable();

            // 100 personnel x 1 ping / 10 s ~= 10 writes/s — keep reads cheap
            $table->index(['person_id', 'captured_at']);
            $table->index('captured_at');
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_pings');
    }
};
