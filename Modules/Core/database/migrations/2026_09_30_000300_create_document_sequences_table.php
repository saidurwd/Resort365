<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document numbering (RSV-2026-00001, INV-…) per tenant, document type and optionally property.
 * Numbers are taken under a row lock (Core DocumentNumbers). TODO(step-0.8): property foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->unsignedBigInteger('property_scope')->storedAs('coalesce(property_id, 0)');
            $table->string('document_type', 50);
            $table->string('prefix', 20);
            $table->string('format', 100);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('reset', 10)->default('never');
            $table->unsignedSmallInteger('period')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'property_scope', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
