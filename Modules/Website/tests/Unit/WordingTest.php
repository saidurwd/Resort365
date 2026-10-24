<?php

/*
| The website's wording of prices, policies and offers (Step 8.2a), in English and Bangla.
*/
use Modules\Rates\DTOs\CancellationRuleData;
use Modules\Rates\DTOs\CancellationTerms;
use Modules\Rates\DTOs\DepositTerms;
use Modules\Rates\DTOs\WebsiteOffer;
use Modules\Rates\Enums\BalanceDueRule;
use Modules\Rates\Enums\CancellationChargeType;
use Modules\Rates\Enums\DepositType;
use Modules\Rates\Enums\DiscountType;
use Modules\Website\Services\OfferWording;
use Modules\Website\Services\PolicyWording;
use Modules\Website\Services\Prices;
use Tests\TestCase;

uses(TestCase::class);

it('formats amounts without needless decimals', function (): void {
    expect(Prices::format('6000.00', 'BDT'))->toBe('BDT 6,000')
        ->and(Prices::format('12345.5', 'BDT'))->toBe('BDT 12,345.50')
        ->and(Prices::format('-250.75', 'USD'))->toBe('-USD 250.75')
        ->and([Prices::number('30.00'), Prices::number('12.50'), Prices::number('100')])->toBe(['30', '12.5', '100']);
});

it('words a deposit policy', function (): void {
    $terms = new DepositTerms(DepositType::Percentage, '20', '30.00', null, null, 30, true, BalanceDueRule::DaysBeforeArrival, 7, 24);

    expect(PolicyWording::deposit($terms, 'BDT'))->toBe([
        'A deposit of 30% of the stay secures the booking.',
        'Pay it within 30 minutes of booking, or the booking is released.',
        'The balance is due 7 days before arrival.',
        'A booking made less than 24 hours before arrival is paid in full.',
    ])->and(PolicyWording::deposit(new DepositTerms(DepositType::FixedAmount, null, '0', null, '5000.00', 1440, true, BalanceDueRule::AtCheckIn), 'BDT'))->toBe([
        'A deposit of BDT 5,000 secures the booking.',
        'Pay it within one day of booking, or the booking is released.',
        'The balance is paid on arrival.',
    ])->and(PolicyWording::deposit(new DepositTerms(DepositType::None, null, '0', null, null, 0, false, BalanceDueRule::AtCheckIn), 'BDT'))
        ->toBe(['No deposit is needed.', 'The balance is paid on arrival.']);
});

it('words a cancellation policy, earliest period first', function (): void {
    $terms = new CancellationTerms([
        new CancellationRuleData(0, 6, CancellationChargeType::PercentOfDeposit, '100'),
        new CancellationRuleData(15, null, CancellationChargeType::PercentOfTotal, '0'),
        new CancellationRuleData(7, 14, CancellationChargeType::Nights, '2'),
    ], CancellationChargeType::Fixed, '3000');

    expect(PolicyWording::cancellation($terms, 'BDT'))->toBe([
        'Cancelled 15 or more days before arrival: free of charge',
        'Cancelled 7 to 14 days before arrival: the price of 2 nights',
        'Cancelled 6 days or fewer before arrival: 100% of the deposit',
        'If you do not arrive: BDT 3,000',
    ])->and(PolicyWording::cancellation(new CancellationTerms([new CancellationRuleData(0, null, CancellationChargeType::PercentOfTotal, '100')]), 'BDT'))
        ->toBe(['Cancelled at any time: 100% of the stay'])
        ->and(PolicyWording::cancellation(new CancellationTerms([]), 'BDT'))->toBe(['Free cancellation.']);
});

it('words an offer, in Bangla too', function (): void {
    $offer = new WebsiteOffer(1, 'Monsoon', null, 'MONSOON20', DiscountType::Percent, '20.00', '2026-06-01', '2026-09-30', null, 3, 14);

    expect(OfferWording::discount($offer, 'BDT'))->toBe('20% off')
        ->and(OfferWording::conditions($offer))->toBe([
            'For stays from 1 Jun 2026 to 30 Sep 2026.', 'Stay at least 3 nights.', 'Book at least 14 days ahead.', 'Use the code MONSOON20 when you book.',
        ])
        ->and(OfferWording::discount(new WebsiteOffer(2, 'Early', null, null, DiscountType::FixedPerNight, '1000.00', null, null, '2026-12-31', null, null), 'BDT'))->toBe('BDT 1,000 off each night');

    app()->setLocale('bn');
    expect(OfferWording::discount($offer, 'BDT'))->toBe('20% ছাড়')
        ->and(OfferWording::conditions($offer)[1])->toBe('অন্তত 3 রাত থাকুন।');
    app()->setLocale('en');
});
