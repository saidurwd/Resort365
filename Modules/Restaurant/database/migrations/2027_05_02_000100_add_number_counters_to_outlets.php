<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The outlet keeps its last order, kitchen-ticket and bill numbers (Step 7.5): numbering locks only the outlet row and counts on, so
 * two terminals never take the same number and never lock each other's bills (found by the load test). Filled from the existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table): void {
            $table->date('numbers_date')->nullable()->after('bill_prefix');
            $table->unsignedInteger('last_order_no')->default(0)->after('numbers_date');
            $table->unsignedInteger('last_kot_no')->default(0)->after('last_order_no');
            $table->unsignedInteger('last_bill_sequence')->default(0)->after('last_kot_no');
        });

        foreach (DB::table('outlets')->pluck('id') as $outletId) {
            $date = DB::table('pos_orders')->where('outlet_id', $outletId)->max('business_date');

            DB::table('outlets')->where('id', $outletId)->update([
                'numbers_date' => $date,
                'last_order_no' => $date !== null ? DB::table('pos_orders')->where('outlet_id', $outletId)->where('business_date', $date)->count() : 0,
                'last_kot_no' => $date !== null ? (int) DB::table('kots')->where('outlet_id', $outletId)->where('business_date', $date)->max('kot_no') : 0,
                'last_bill_sequence' => (int) DB::table('pos_bills')->where('outlet_id', $outletId)->max('sequence'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table): void {
            $table->dropColumn(['numbers_date', 'last_order_no', 'last_kot_no', 'last_bill_sequence']);
        });
    }
};
