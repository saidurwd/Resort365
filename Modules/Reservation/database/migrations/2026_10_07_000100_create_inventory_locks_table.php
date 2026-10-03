<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per room per night that is taken (ARCHITECTURE §6.1, §8.3). UNIQUE(room_id, stay_date)
 * is what makes double booking impossible: a whole-cottage booking locks every room of the
 * cottage, so it collides with any booked room and the other way round. Out-of-order and owner
 * blocks use the same table, so it is the single source of truth for availability.
 * reservation_id / reservation_item_id / block_id get their foreign keys with their tables (Step 1.6+).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('stay_date');
            $table->string('lock_type', 20);
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->unsignedBigInteger('reservation_item_id')->nullable();
            $table->unsignedBigInteger('block_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'stay_date']);
            $table->index(['tenant_id', 'property_id', 'stay_date']);
            $table->index(['reservation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locks');
    }
};
