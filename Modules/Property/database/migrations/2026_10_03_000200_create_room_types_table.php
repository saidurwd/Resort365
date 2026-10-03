<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room types of a property, e.g. "Deluxe King" (ARCHITECTURE §5.4, §8.3). max_occupancy caps
 * adults plus children in any room of the type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('base_occupancy');
            $table->unsignedTinyInteger('max_adults');
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->unsignedTinyInteger('max_occupancy');
            $table->string('bed_configuration', 100)->nullable();
            $table->decimal('size_sqm', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['tenant_id', 'property_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
