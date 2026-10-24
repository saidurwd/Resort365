<?php

namespace Modules\Website\Services;

use App\Support\Tenancy\ModuleAccess;
use Modules\Core\Contracts\PublicPhotos;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\Contracts\UnitShowcases;
use Modules\Property\DTOs\PropertySummary;
use Modules\Property\DTOs\UnitShowcase;
use Modules\Property\Enums\UnitKind;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\CancellationPolicySummary;
use Modules\Rates\DTOs\DepositPolicySummary;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Rates\DTOs\WebsiteOffer;
use Modules\Restaurant\Contracts\OutletDirectory;
use Modules\Restaurant\DTOs\OutletListing;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Models\WebsiteUnit;

/**
 * What the public website's pages show (Step 8.2a), gathered from the other modules through their contracts and this module's
 * texts, in the current language. Plain arrays for the views.
 *
 * @phpstan-type Unit array{key: string, kind: string, id: int, slug: string, name: string, headline: string, description: string, max_occupancy: int, max_adults: int|null, max_children: int|null, bedrooms: int, bed_configuration: string|null, size_sqm: string|null, units: int, amenities: list<array{name: string, icon: string|null}>, photos: list<array{id: int, thumb: string, web: string, alt: string}>, from: array{amount: string, plus_tax: bool}|null, featured: bool}
 */
class SitePages
{
    public function __construct(
        private readonly UnitShowcases $showcases,
        private readonly PropertyDirectory $properties,
        private readonly RateLookup $rates,
        private readonly PublicPhotos $photos,
        private readonly FromPrices $prices,
        private readonly ModuleAccess $modules,
    ) {}

    public function property(WebsiteSite $site): ?PropertySummary
    {
        return $this->properties->find($site->property_id);
    }

    /**
     * The room types and cottage types shown on the site, in the site's order.
     *
     * @return list<Unit>
     */
    public function units(WebsiteSite $site): array
    {
        $showcases = $this->showcases->forProperty($site->property_id);
        $settings = WebsiteUnit::query()->where('website_site_id', $site->id)->get()->keyBy('unit_key');
        $prices = $this->prices->forUnits($site->property_id, array_map(fn (UnitShowcase $unit): string => $unit->key(), $showcases));
        $units = [];

        foreach ($showcases as $position => $showcase) {
            $setting = $settings->get($showcase->key());

            if ($setting instanceof WebsiteUnit && $setting->is_hidden) {
                continue;
            }

            $units[] = ['order' => [$setting->sort_order ?? 0, $position], 'unit' => $this->unit($showcase, $setting, $prices[$showcase->key()] ?? null)];
        }

        usort($units, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_column($units, 'unit');
    }

    /**
     * The units featured on the home page: those marked, else the first three.
     *
     * @param  list<Unit>  $units
     * @return list<Unit>
     */
    public function featured(array $units): array
    {
        $featured = array_values(array_filter($units, fn (array $unit): bool => $unit['featured']));

        return $featured !== [] ? $featured : array_slice($units, 0, 3);
    }

    /**
     * One unit by its kind (`room` or `cottage`) and id, as shown on its page, or null when it is not on the site.
     *
     * @return Unit|null
     */
    public function find(WebsiteSite $site, string $kind, int $id): ?array
    {
        foreach ($this->units($site) as $unit) {
            if ($unit['slug'] === $kind && $unit['id'] === $id) {
                return $unit;
            }
        }

        return null;
    }

    /**
     * The site's own photos (the first ones are the hero).
     *
     * @return list<array{id: int, thumb: string, web: string, alt: string}>
     */
    public function sitePhotos(WebsiteSite $site): array
    {
        return $this->photos->many($site->getMedia('photos')->map(fn ($media): int => (int) $media->id)->values()->all());
    }

    /**
     * Every photo for the Gallery page: the site's, then each unit's.
     *
     * @param  list<Unit>  $units
     * @return list<array{id: int, thumb: string, web: string, alt: string}>
     */
    public function gallery(WebsiteSite $site, array $units): array
    {
        $seen = [];
        $photos = [];

        foreach ([$this->sitePhotos($site), ...array_column($units, 'photos')] as $group) {
            foreach ($group as $photo) {
                if (! isset($seen[$photo['id']])) {
                    $seen[$photo['id']] = true;
                    $photos[] = $photo;
                }
            }
        }

        return $photos;
    }

    /**
     * The promotions shown on the site, worded for guests.
     *
     * @return list<array{name: string, description: string|null, discount: string, conditions: list<string>, code: string|null}>
     */
    public function offers(WebsiteSite $site, string $currency): array
    {
        return array_map(fn (WebsiteOffer $offer): array => [
            'name' => $offer->name, 'description' => $offer->description, 'discount' => OfferWording::discount($offer, $currency),
            'conditions' => OfferWording::conditions($offer), 'code' => $offer->code,
        ], $this->rates->websiteOffers($site->property_id));
    }

    /**
     * The deposit and cancellation terms of the plans sold online, one entry per distinct pair of policies, with the plans it covers.
     *
     * @return list<array{plans: list<string>, deposit: list<string>, cancellation: list<string>}>
     */
    public function policies(WebsiteSite $site, string $currency): array
    {
        $groups = [];

        foreach ($this->rates->ratePlans($site->property_id) as $plan) {
            /** @var RatePlanSummary $plan */
            if (! $plan->sellsThrough('online')) {
                continue;
            }

            $deposit = $this->rates->depositPolicy($plan->id);
            $cancellation = $this->rates->cancellationPolicy($plan->id);
            $key = ($deposit->id ?? 0).':'.($cancellation->id ?? 0);

            $groups[$key] ??= [
                'plans' => [],
                'deposit' => $deposit instanceof DepositPolicySummary ? PolicyWording::deposit($deposit->terms, $currency) : [__('No deposit is needed.')],
                'cancellation' => $cancellation instanceof CancellationPolicySummary ? PolicyWording::cancellation($cancellation->terms, $currency) : [__('Free cancellation.')],
            ];
            $groups[$key]['plans'][] = $plan->name;
        }

        return array_values($groups);
    }

    /**
     * The property's outlets for the Dining page; empty when the company has no restaurant module.
     *
     * @return list<array{name: string, type: string, hours: list<array{day: string, time: string}>}>
     */
    public function dining(WebsiteSite $site): array
    {
        if (! $this->modules->enabled('restaurant') || ! app()->bound(OutletDirectory::class)) {
            return [];
        }

        $days = ['mon' => __('Monday'), 'tue' => __('Tuesday'), 'wed' => __('Wednesday'), 'thu' => __('Thursday'), 'fri' => __('Friday'), 'sat' => __('Saturday'), 'sun' => __('Sunday')];

        return array_map(fn (OutletListing $outlet): array => [
            'name' => $outlet->name,
            'type' => $outlet->type->label(),
            'hours' => array_values(array_filter(array_map(function (string $day, string $label) use ($outlet): ?array {
                $hours = $outlet->openingHours[$day] ?? null;

                return is_array($hours) && $hours['open'] !== null && $hours['close'] !== null ? ['day' => $label, 'time' => $hours['open'].'–'.$hours['close']] : null;
            }, array_keys($days), $days))),
        ], app(OutletDirectory::class)->forProperty($site->property_id));
    }

    /**
     * @param  array{amount: string, plus_tax: bool}|null  $from
     * @return Unit
     */
    private function unit(UnitShowcase $showcase, ?WebsiteUnit $setting, ?array $from): array
    {
        $headline = $setting?->translated('headline') ?? '';
        $description = $setting?->translated('description') ?? '';

        return [
            'key' => $showcase->key(),
            'kind' => $showcase->kind->value,
            'id' => $showcase->id,
            'slug' => $showcase->kind === UnitKind::RoomType ? 'room' : 'cottage',
            'name' => $showcase->name,
            'headline' => $headline,
            'description' => $description !== '' ? $description : (string) $showcase->description,
            'max_occupancy' => $showcase->maxOccupancy,
            'max_adults' => $showcase->maxAdults,
            'max_children' => $showcase->maxChildren,
            'bedrooms' => $showcase->bedrooms,
            'bed_configuration' => $showcase->bedConfiguration,
            'size_sqm' => $showcase->sizeSqm,
            'units' => $showcase->units,
            'amenities' => $showcase->amenities,
            'photos' => $this->photos->many($showcase->photoIds),
            'from' => $from,
            'featured' => $setting instanceof WebsiteUnit && $setting->is_featured,
        ];
    }
}
