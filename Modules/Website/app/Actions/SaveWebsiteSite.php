<?php

namespace Modules\Website\Actions;

use App\Support\Actions\Action;
use Modules\Website\Models\WebsiteSite;

/**
 * Creates or changes a property's website (Step 8.2a): address, look, contact details and texts per language. Publishing records when
 * the site went public; texts left empty in every language are stored as null.
 */
class SaveWebsiteSite extends Action
{
    /**
     * @param  array<string, mixed>  $data  validated fields; texts as [locale => text]
     */
    public function handle(?WebsiteSite $site, int $propertyId, array $data): WebsiteSite
    {
        $site ??= new WebsiteSite(['property_id' => $propertyId]);

        foreach (WebsiteSite::TEXTS as $text) {
            $values = array_filter((array) ($data[$text] ?? []), fn (mixed $value): bool => is_string($value) && trim($value) !== '');
            $data[$text] = $values === [] ? null : array_map(trim(...), $values);
        }

        $publish = (bool) ($data['is_published'] ?? false);
        $site->fill([...$data, 'slug' => strtolower((string) $data['slug']), 'published_at' => $publish ? ($site->published_at ?? now()) : null]);
        $site->save();

        return $site;
    }
}
