<?php

/*
| POS numbering under the outlet's counters (Step 7.5): order, kitchen-ticket and bill numbers count on from the outlet row (so
| concurrent terminals never repeat one or lock each other's bills), respect numbers stored without the counters, start again on a new
| business date, and leave today's counters alone for a late number on an earlier date.
*/
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Services\PosNumbers;

require_once __DIR__.'/../Support/pos-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/** The Main Restaurant of the order setup, read fresh. */
function pnOutlet(): Outlet
{
    $id = orderSetup()['terminal']->outlet_id;

    return booking(fn (): Outlet => Outlet::query()->findOrFail($id));
}

/**
 * Runs the numbering in a transaction, as the actions do.
 *
 * @template T
 *
 * @param  Closure(PosNumbers, Outlet): T  $take
 * @return T
 */
function pnTake(Outlet $outlet, Closure $take): mixed
{
    return booking(fn () => DB::transaction(fn () => $take(app(PosNumbers::class), Outlet::query()->findOrFail($outlet->id))));
}

it('counts order and ticket numbers on from the outlet\'s counters, per business date', function (): void {
    $outlet = pnOutlet();

    expect(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextOrderNo($o, '2026-11-10')))->toBe('MR-0001')
        ->and(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextOrderNo($o, '2026-11-10')))->toBe('MR-0002')
        ->and(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextKotNo($o, '2026-11-10')))->toBe(1)
        ->and(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextKotNo($o, '2026-11-10')))->toBe(2);

    $stored = booking(fn (): Outlet => Outlet::query()->findOrFail($outlet->id));
    expect($stored->numbers_date?->toDateString())->toBe('2026-11-10')->and($stored->last_order_no)->toBe(2)->and($stored->last_kot_no)->toBe(2);

    // The next business date starts both again.
    expect(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextOrderNo($o, '2026-11-11')))->toBe('MR-0001')
        ->and(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextKotNo($o, '2026-11-11')))->toBe(1);
});

it('respects numbers stored without the counters, and leaves today\'s counters alone for an earlier date', function (): void {
    $outlet = pnOutlet();
    booking(function () use ($outlet): void {
        foreach (range(1, 3) as $n) {
            PosOrder::factory()->create(['outlet_id' => $outlet->id, 'business_date' => '2026-11-10', 'order_no' => 'MR-H00'.$n]);
        }
    });

    expect(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextOrderNo($o, '2026-11-10')))->toBe('MR-0004')
        ->and(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextOrderNo($o, '2026-11-12')))->toBe('MR-0001')
        // An earlier date counts the stored orders (the numbers above were only taken, no order was saved).
        ->and(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextOrderNo($o, '2026-11-10')))->toBe('MR-0004');

    $stored = booking(fn (): Outlet => Outlet::query()->findOrFail($outlet->id));
    expect($stored->numbers_date?->toDateString())->toBe('2026-11-12')->and($stored->last_order_no)->toBe(1);
});

it('counts bill numbers on for ever, past bills stored without the counter', function (): void {
    $outlet = pnOutlet();

    expect(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextBill($o)))->toBe([1, 'MR-B000001'])
        ->and(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextBill($o)))->toBe([2, 'MR-B000002']);

    // A bill stored by an older release or the demo history, beyond the counter.
    booking(fn () => PosBill::factory()->create(['property_id' => $outlet->property_id, 'outlet_id' => $outlet->id, 'sequence' => 7, 'bill_no' => 'MR-B000007']));

    expect(pnTake($outlet, fn ($numbers, Outlet $o) => $numbers->nextBill($o)))->toBe([8, 'MR-B000008'])
        ->and(booking(fn (): int => Outlet::query()->findOrFail($outlet->id)->last_bill_sequence))->toBe(8);
});
