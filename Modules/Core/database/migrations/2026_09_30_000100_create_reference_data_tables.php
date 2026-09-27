<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central reference data (ARCHITECTURE §5.1, §9.3): ISO 3166 countries, ISO 4217 currencies, timezones.
 * Filled by ReferenceDataSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->char('code', 2)->primary();
            $table->char('iso3', 3)->unique();
            $table->char('numeric_code', 3)->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table): void {
            $table->char('code', 3)->primary();
            $table->string('name');
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->timestamps();
        });

        Schema::create('timezones', function (Blueprint $table): void {
            $table->string('name', 64)->primary();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('utc_offset', 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timezones');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('countries');
    }
};
