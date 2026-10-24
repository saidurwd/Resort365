<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions shown on the public website's Offers page (Step 8.2a). Off by default, so code promotions stay private.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->boolean('show_on_website')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropColumn('show_on_website');
        });
    }
};
