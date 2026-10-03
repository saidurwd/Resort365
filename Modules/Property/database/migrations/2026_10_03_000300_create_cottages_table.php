<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cottages of a property (ARCHITECTURE §5.4, §6.1, §8.3). booking_mode: rooms_only | whole_only | both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cottages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cottage_type_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('zone', 100)->nullable();
            $table->string('booking_mode', 20)->default('both');
            $table->unsignedSmallInteger('max_occupancy_override')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['tenant_id', 'property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cottages');
    }
};
