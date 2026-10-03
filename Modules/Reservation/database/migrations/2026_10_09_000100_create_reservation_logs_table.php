<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reservation's history (ARCHITECTURE §6.7): one row per change, with a readable description and
 * the old and new values. The activity log keeps the column-level audit as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('description');
            $table->json('changes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'reservation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_logs');
    }
};
