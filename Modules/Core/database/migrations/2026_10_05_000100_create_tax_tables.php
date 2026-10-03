<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax engine (ARCHITECTURE §5.1): the tenant's taxes and charges, and the tax categories that
 * group them (Room, Food & beverage…). A percent tax stores its rate (e.g. 15.0000); a fixed tax
 * its amount per unit. Taxes are applied in sort_order; a compound tax is calculated on the net
 * amount plus the taxes before it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('type', 20);
            $table->decimal('rate', 15, 4);
            $table->boolean('is_compound')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('tax_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('tax_category_taxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_id')->constrained()->restrictOnDelete();

            $table->unique(['tax_category_id', 'tax_id']);
            $table->index(['tenant_id', 'tax_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_category_taxes');
        Schema::dropIfExists('tax_categories');
        Schema::dropIfExists('taxes');
    }
};
