<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\StockIssueLine;

/**
 * @extends Factory<StockIssueLine>
 */
class StockIssueLineFactory extends Factory
{
    protected $model = StockIssueLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issue = StockIssue::factory()->create();

        return ['property_id' => $issue->property_id, 'stock_issue_id' => $issue->id, 'item_id' => Item::factory(), 'quantity' => '2', 'unit_cost' => '10', 'total_cost' => '20'];
    }
}
