<?php

namespace Modules\Website\Actions;

use App\Support\Actions\Action;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Models\WebsiteUnit;

/**
 * How the property's room types and cottage types appear on its website (Step 8.2a): texts per language, featured, hidden and order.
 * One row per unit key; all in one transaction.
 */
class SaveWebsiteUnits extends Action
{
    /**
     * @param  array<string, array{headline?: array<string, string|null>, description?: array<string, string|null>, is_featured?: bool, is_hidden?: bool, sort_order?: int}>  $rows  unit key => settings
     */
    public function handle(WebsiteSite $site, array $rows): void
    {
        $this->transaction(function () use ($site, $rows): void {
            foreach ($rows as $unitKey => $row) {
                $text = fn (string $field): ?array => ($values = array_filter((array) ($row[$field] ?? []), fn (mixed $value): bool => is_string($value) && trim($value) !== '')) === []
                    ? null : array_map(trim(...), $values);

                WebsiteUnit::query()->updateOrCreate(
                    ['website_site_id' => $site->id, 'unit_key' => $unitKey],
                    ['property_id' => $site->property_id, 'headline' => $text('headline'), 'description' => $text('description'),
                        'is_featured' => (bool) ($row['is_featured'] ?? false), 'is_hidden' => (bool) ($row['is_hidden'] ?? false), 'sort_order' => (int) ($row['sort_order'] ?? 0)],
                );
            }
        });
    }
}
