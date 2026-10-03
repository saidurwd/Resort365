<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms, the atomic unit of inventory (ARCHITECTURE §6.1, §8.3). Every room belongs to one cottage.
 * Empty max_adults / max_children use the room type's values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cottage_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('number', 20);
            $table->string('name')->nullable();
            $table->string('floor', 20)->nullable();
            $table->unsignedTinyInteger('max_adults')->nullable();
            $table->unsignedTinyInteger('max_children')->nullable();
            $table->string('housekeeping_status', 20)->default('clean');
            $table->string('occupancy_status', 20)->default('vacant');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'number']);
            $table->index(['tenant_id', 'property_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
