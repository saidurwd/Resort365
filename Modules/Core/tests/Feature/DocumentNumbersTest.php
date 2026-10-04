<?php

use App\Actions\Tenancy\CreateTenant;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\Enums\SequenceReset;
use Modules\Core\Models\DocumentSequence;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley']));
});

/**
 * @return list<string>
 */
function take(string $tenant, string $type, int $count, ?int $property = null, string $date = '2026-06-15'): array
{
    return app(TenantContext::class)->run(tenant($tenant), fn (): array => array_map(
        fn (): string => app(DocumentNumbers::class)->next($type, $property, CarbonImmutable::parse($date)),
        range(1, $count),
    ));
}

it('numbers documents in sequence with the default format', function (): void {
    expect(take('sunrise', 'reservation', 3))->toBe(['RSV-2026-00001', 'RSV-2026-00002', 'RSV-2026-00003'])
        ->and(take('sunrise', 'invoice', 1))->toBe(['INV-2026-00001']);
});

it('keeps separate sequences per tenant and per property', function (): void {
    take('sunrise', 'reservation', 2);

    expect(take('greenvalley', 'reservation', 1))->toBe(['RSV-2026-00001'])
        ->and(take('sunrise', 'reservation', 1, property: 7))->toBe(['RSV-2026-00001'])
        ->and(take('sunrise', 'reservation', 1))->toBe(['RSV-2026-00003']);
});

it('restarts yearly sequences in a new year only', function (): void {
    take('sunrise', 'reservation', 2, date: '2026-12-31');
    expect(take('sunrise', 'reservation', 1, date: '2027-01-01'))->toBe(['RSV-2027-00001']);

    app(TenantContext::class)->run(tenant('sunrise'), fn () => DocumentSequence::query()->where('document_type', 'invoice')->delete());
    app(TenantContext::class)->run(tenant('sunrise'), fn () => DocumentSequence::factory()->create([
        'document_type' => 'invoice', 'prefix' => 'INV', 'format' => '{PREFIX}{SEQ}', 'reset' => SequenceReset::Never, 'next_number' => 41, 'period' => 2025,
    ]));
    expect(take('sunrise', 'invoice', 1, date: '2027-03-01'))->toBe(['INV41']);
});

it('creates every sequence for a new tenant, and a missing one on first use', function (): void {
    $tenant = CreateTenant::make()->handle('lakeside', 'Lakeside Retreat');

    app(TenantContext::class)->run($tenant, function (): void {
        expect(DocumentSequence::query()->whereNull('property_id')->pluck('document_type')->sort()->values()->all())
            ->toBe(['credit_note', 'folio', 'goods_receipt', 'invoice', 'journal', 'payment', 'purchase_order', 'quote', 'reservation']);

        DocumentSequence::query()->where('document_type', 'journal')->delete();
    });

    expect(take('lakeside', 'journal', 2))->toBe(['JV-2026-00001', 'JV-2026-00002']);
});

it('renders every format token', function (): void {
    expect(app(DocumentNumbers::class)->format('{PREFIX}/{YY}{MM}{DD}/{SEQ:3}-{SEQ}', 'JV', 7, CarbonImmutable::parse('2026-09-05')))
        ->toBe('JV/260905/007-7');
});

it('refuses unknown document types and duplicate registrations', function (): void {
    expect(fn () => take('sunrise', 'nope', 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(DocumentNumbers::class)->register(new DocumentType('reservation', 'Again', 'X')))->toThrow(InvalidArgumentException::class);
});

it('edits numbering from the setup screen with a preview', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));

    get(tenantUrl('sunrise', '/core/document-sequences'))->assertOk()->assertSee('RSV-'.now()->format('Y').'-00001');

    put(tenantUrl('sunrise', '/core/document-sequences/reservation'), [
        'prefix' => 'SCB', 'format' => '{PREFIX}-{YY}-{SEQ:4}', 'next_number' => 500, 'reset' => 'never',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(take('sunrise', 'reservation', 2))->toBe(['SCB-26-0500', 'SCB-26-0501']);

    put(tenantUrl('sunrise', '/core/document-sequences/reservation'), ['prefix' => 'X', 'format' => 'NOSEQ', 'next_number' => 1, 'reset' => 'never'])
        ->assertSessionHasErrors('format');
    get(tenantUrl('sunrise', '/core/document-sequences/nope/edit'))->assertNotFound();
});

it('honours an edited next number on a sequence that already exists', function (): void {
    app(TenantContext::class)->run(tenant('sunrise'), fn () => app(DocumentNumbers::class)->ensure('invoice'));
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));

    put(tenantUrl('sunrise', '/core/document-sequences/invoice'), ['prefix' => 'INV', 'format' => '{PREFIX}-{SEQ:4}', 'next_number' => 100, 'reset' => 'yearly'])
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'INV-0100'));

    get(tenantUrl('sunrise', '/core/document-sequences'))->assertSee('INV-0100');
    expect(take('sunrise', 'invoice', 1, date: now()->toDateString()))->toBe(['INV-0100']);
});

it('lets only permitted roles change numbering', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Auditor));
    get(tenantUrl('sunrise', '/core/document-sequences'))->assertOk();
    get(tenantUrl('sunrise', '/core/document-sequences/reservation/edit'))->assertForbidden();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/core/document-sequences'))->assertForbidden();
});
