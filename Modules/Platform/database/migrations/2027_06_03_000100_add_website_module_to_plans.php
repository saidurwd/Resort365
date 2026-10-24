<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The public website (Step 8.2a) is part of every plan: add it to the module lists already stored, so that changing a tenant's plan
 * later does not switch it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('plans')->get(['id', 'modules']) as $plan) {
            $modules = json_decode((string) $plan->modules, true);

            if (is_array($modules) && in_array('reservation', $modules, true) && ! in_array('website', $modules, true)) {
                DB::table('plans')->where('id', $plan->id)->update(['modules' => json_encode([...$modules, 'website'])]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('plans')->get(['id', 'modules']) as $plan) {
            $modules = json_decode((string) $plan->modules, true);

            if (is_array($modules) && in_array('website', $modules, true)) {
                DB::table('plans')->where('id', $plan->id)->update(['modules' => json_encode(array_values(array_diff($modules, ['website'])))]);
            }
        }
    }
};
