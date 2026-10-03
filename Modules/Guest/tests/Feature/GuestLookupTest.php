<?php

/*
| Step 1.2 "Done when": the lookup is fast with 10,000 seeded guests.
*/
use App\Models\Tenant;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\CompanySummary;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Guest\Models\Company;
use Modules\Guest\Models\Guest;
use Modules\Guest\Models\TravelAgent;
use Modules\Guest\Services\GuestSearch;
use Modules\Guest\Tests\Support\GuestSetup;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    GuestSetup::tenant();
});

/**
 * @return list<string>
 */
function lookupNames(string $term): array
{
    return GuestSetup::run(fn (): array => array_map(fn (GuestSummary $guest): string => $guest->name, app(GuestLookup::class)->search($term)));
}

it('narrows a search to some guests: a list of ids or a query selecting ids', function (): void {
    $kept = GuestSetup::guest(['first_name' => 'Ayesha', 'last_name' => 'Siddique']);
    GuestSetup::guest(['first_name' => 'Ayesha', 'last_name' => 'Akter']);

    $names = fn (array|Builder $among): array => GuestSetup::run(fn (): array => array_map(
        fn (GuestSummary $guest): string => $guest->name, app(GuestLookup::class)->search('ayesha', 10, $among)));

    expect($names([$kept->id]))->toBe(['Ayesha Siddique'])
        ->and($names(DB::table('guests')->select('id')->where('last_name', 'Siddique')))->toBe(['Ayesha Siddique'])
        ->and($names([]))->toBe([])
        ->and(lookupNames('ayesha'))->toHaveCount(2);
});

it('finds guests by phone (any format), email, ID number and name prefixes', function (): void {
    GuestSetup::guest(['title' => 'Mr', 'first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '01711000001', 'email' => 'rahim@example.com',
        'id_type' => 'passport', 'id_number' => 'AB1234567']);
    GuestSetup::guest(['first_name' => 'Rahima', 'last_name' => 'Akter', 'phone' => '01819000002']);
    GuestSetup::guest(['first_name' => 'Karim', 'last_name' => 'Uddin']);

    expect(lookupNames('01711-000001'))->toBe(['Mr Rahim Uddin'])
        ->and(lookupNames('+8801711'))->toBe(['Mr Rahim Uddin'])
        ->and(lookupNames('RAHIM@example'))->toBe(['Mr Rahim Uddin'])
        ->and(lookupNames('ab 1234567'))->toBe(['Mr Rahim Uddin'])
        ->and(lookupNames('rah'))->toBe(['Mr Rahim Uddin', 'Rahima Akter'])
        ->and(lookupNames('rah udd'))->toBe(['Mr Rahim Uddin'])
        ->and(lookupNames('uddin'))->toBe(['Karim Uddin', 'Mr Rahim Uddin'])
        ->and(lookupNames('50%'))->toBe([]);
});

it('looks up companies and travel agents', function (): void {
    [$company, $agent] = GuestSetup::run(fn (): array => [
        Company::factory()->create(['name' => 'Meghna Group', 'credit_limit' => '500000.00']),
        TravelAgent::factory()->create(['code' => 'SBT', 'name' => 'Sundarban Tours', 'commission_percent' => '12.50']),
    ]);
    GuestSetup::run(fn () => Company::factory()->create(['name' => 'Meghna Old', 'is_active' => false]));

    $lookup = app(GuestLookup::class);
    GuestSetup::run(function () use ($lookup, $company, $agent): void {
        expect(array_column(array_map(fn (CompanySummary $c) => $c->toArray(), $lookup->searchCompanies('megh')), 'name'))->toBe(['Meghna Group'])
            ->and($lookup->findCompany($company->id)?->creditLimit)->toBe('500000.00')
            ->and($lookup->searchTravelAgents('sbt')[0]->name)->toBe('Sundarban Tours')
            ->and($lookup->findTravelAgent($agent->id)?->commissionPercent)->toBe('12.50');
    });
});

it('stays fast and uses indexes with 10,000 guests', function (): void {
    $tenant = tenant('sunrise');
    $now = now()->toDateTimeString();

    foreach (array_chunk(range(1, 10_000), 1000) as $chunk) {
        DB::table('guests')->insert(array_map(fn (int $i): array => [
            'tenant_id' => $tenant->id, 'first_name' => ['Rahim', 'Karim', 'Nusrat', 'Farzana', 'Tanvir'][$i % 5].$i, 'last_name' => ['Uddin', 'Ahmed', 'Hossain'][$i % 3],
            'email' => "guest{$i}@example.com", 'phone' => '+88017'.str_pad((string) (10_000_000 + $i), 8, '0', STR_PAD_LEFT),
            'vip_level' => 'none', 'created_at' => $now, 'updated_at' => $now,
        ], $chunk));
    }
    // Another tenant's guests must not slow the search down or show up.
    $other = Tenant::factory()->create();
    DB::table('guests')->insert(['tenant_id' => $other->id, 'first_name' => 'Rahim1', 'phone' => '+8801710000001', 'vip_level' => 'none']);
    DB::statement('ANALYZE TABLE guests');

    GuestSetup::run(function (): void {
        $lookup = app(GuestLookup::class);

        foreach (['01710005000', 'guest5000@example.com', 'Rahim5', 'tanvir1234 ahm'] as $term) {
            $start = hrtime(true);
            $found = $lookup->search($term);
            $ms = (hrtime(true) - $start) / 1e6;

            expect($found)->not->toBeEmpty()->and($ms)->toBeLessThan(250.0, "search [{$term}] took {$ms} ms");

            $query = app(GuestSearch::class)->apply(Guest::query(), $term)->limit(10);
            $plan = DB::select('EXPLAIN '.$query->toRawSql());
            $scanned = array_sum(array_map(fn (object $row): int => (int) $row->rows, $plan));

            expect(array_filter(array_column($plan, 'key')))->not->toBeEmpty("search [{$term}] uses no index")
                ->and($scanned)->toBeLessThan(2500, "search [{$term}] reads {$scanned} rows");
        }

        expect(array_map(fn (GuestSummary $guest): string => (string) $guest->phone, $lookup->search('01710000001')))->toBe(['+8801710000001']);
    });
});
