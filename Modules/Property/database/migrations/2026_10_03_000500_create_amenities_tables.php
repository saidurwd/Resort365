<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant's amenities catalogue and its links to cottage types and room types (ARCHITECTURE §5.4, §8.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('icon', 50)->nullable();
            $table->string('category', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'category']);
        });

        Schema::create('amenity_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type', 50);
            $table->unsignedBigInteger('linkable_id');

            $table->unique(['amenity_id', 'linkable_type', 'linkable_id']);
            $table->index(['tenant_id', 'linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_links');
        Schema::dropIfExists('amenities');
    }
};
