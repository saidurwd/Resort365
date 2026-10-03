<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Properties (resorts) of a tenant (ARCHITECTURE §5.4, §8.3). Property-level settings live in
 * Core's settings table, so there is no settings column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2);
            $table->string('timezone', 64);
            $table->char('currency_code', 3);
            $table->time('check_in_time')->default('14:00');
            $table->time('check_out_time')->default('12:00');
            $table->date('business_date');
            $table->string('tax_registration_no', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('countries');
            $table->foreign('timezone')->references('name')->on('timezones');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
