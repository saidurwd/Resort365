<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cottage types of a property, e.g. "Family Villa" (ARCHITECTURE §5.4, §8.3). Photos live in the media library.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cottage_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('max_occupancy');
            $table->unsignedTinyInteger('bedrooms')->default(1);
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
        Schema::dropIfExists('cottage_types');
    }
};
