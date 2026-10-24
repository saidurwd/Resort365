<?php

namespace Modules\Website\Services;

use Carbon\CarbonImmutable;
use Modules\Rates\DTOs\WebsiteOffer;
use Modules\Rates\Enums\DiscountType;

/**
 * A promotion in guests' words (Step 8.2a, the website's Offers page): what it takes off and when it applies. Pure.
 */
final class OfferWording
{
    public static function discount(WebsiteOffer $offer, string $currency): string
    {
        $value = Prices::number($offer->discountValue);

        return match ($offer->discountType) {
            DiscountType::Percent => __(':percent% off', ['percent' => $value]),
            DiscountType::FixedPerNight => __(':amount off each night', ['amount' => Prices::format($offer->discountValue, $currency)]),
            DiscountType::FixedPerStay => __(':amount off the stay', ['amount' => Prices::format($offer->discountValue, $currency)]),
        };
    }

    /**
     * @return list<string>
     */
    public static function conditions(WebsiteOffer $offer): array
    {
        $date = fn (string $day): string => CarbonImmutable::parse($day)->locale(app()->getLocale())->translatedFormat('j M Y');
        $lines = [];

        if ($offer->stayFrom !== null && $offer->stayTo !== null) {
            $lines[] = __('For stays from :from to :to.', ['from' => $date($offer->stayFrom), 'to' => $date($offer->stayTo)]);
        } elseif ($offer->stayFrom !== null) {
            $lines[] = __('For stays from :from.', ['from' => $date($offer->stayFrom)]);
        } elseif ($offer->stayTo !== null) {
            $lines[] = __('For stays until :to.', ['to' => $date($offer->stayTo)]);
        }

        if ($offer->minNights !== null && $offer->minNights > 1) {
            $lines[] = __('Stay at least :count nights.', ['count' => $offer->minNights]);
        }

        if ($offer->minAdvanceDays !== null && $offer->minAdvanceDays > 0) {
            $lines[] = __('Book at least :count days ahead.', ['count' => $offer->minAdvanceDays]);
        }

        if ($offer->bookTo !== null) {
            $lines[] = __('Book by :date.', ['date' => $date($offer->bookTo)]);
        }

        if ($offer->code !== null) {
            $lines[] = __('Use the code :code when you book.', ['code' => $offer->code]);
        }

        return $lines;
    }
}
