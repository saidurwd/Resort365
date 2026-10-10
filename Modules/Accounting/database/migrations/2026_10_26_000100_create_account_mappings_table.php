<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account mapping (Step 4.2): the tenant's choice of ledger account for a posting key (guest ledger,
 * `method:cash`, `charge:SPA`…). A key with no row uses its default from the chart's system keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('mapping_key', 60);
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'mapping_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};
