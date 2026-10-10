<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.3: a menu category's sales post to food or beverage revenue. A sub-category without its own class
 * (null) follows its parent; a top-level one without a class counts as food.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->string('revenue_class', 10)->nullable()->after('colour');
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropColumn('revenue_class');
        });
    }
};
