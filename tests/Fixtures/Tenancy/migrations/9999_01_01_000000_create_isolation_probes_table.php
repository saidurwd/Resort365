<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only table for the tenant-isolation harness (registered in Tests\TestCase, never in the real schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isolation_probes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('code', 20);
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isolation_probes');
    }
};
