<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 5.2: an outlet draws its ingredients from a store of the Inventory module (no foreign key: Inventory is optional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table): void {
            $table->unsignedBigInteger('store_id')->nullable()->after('default_tax_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table): void {
            $table->dropColumn('store_id');
        });
    }
};
