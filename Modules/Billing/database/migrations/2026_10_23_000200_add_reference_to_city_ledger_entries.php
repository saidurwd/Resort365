<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * City-ledger entries posted by other modules (Step 3.7: a restaurant bill paid to a company account)
 * point at what they are for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('city_ledger_entries', function (Blueprint $table): void {
            $table->string('reference_type', 40)->nullable()->after('invoice_id');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->index(['tenant_id', 'reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::table('city_ledger_entries', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'reference_type', 'reference_id']);
            $table->dropColumn(['reference_type', 'reference_id']);
        });
    }
};
