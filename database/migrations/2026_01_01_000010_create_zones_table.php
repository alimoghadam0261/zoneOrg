<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['polygon', 'circle', 'rectangle'])->default('polygon');

            // GeoJSON geometry: {"type":"Polygon","coordinates":[[[lng,lat],...]]}
            // circles: {"type":"Point","coordinates":[lng,lat],"properties":{"radius":120}}
            $table->json('geometry');

            // Phase-1 bounding box fast filter
            $table->decimal('min_lat', 10, 8);
            $table->decimal('max_lat', 10, 8);
            $table->decimal('min_lng', 11, 8);
            $table->decimal('max_lng', 11, 8);

            $table->decimal('center_lat', 10, 8)->nullable();
            $table->decimal('center_lng', 11, 8)->nullable();
            $table->unsignedInteger('radius')->nullable(); // metres (circle zones)

            $table->string('color', 9)->default('#6366f1');
            $table->enum('severity_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_active')->default(true);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'severity_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
