<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\Store;

/**
 * @extends Factory<StockIssue>
 */
class StockIssueFactory extends Factory
{
    protected $model = StockIssue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->create();

        return ['property_id' => $store->property_id, 'issue_no' => 'ISS-'.fake()->unique()->numerify('####-#####'), 'store_id' => $store->id, 'issued_on' => now()->toDateString()];
    }
}
