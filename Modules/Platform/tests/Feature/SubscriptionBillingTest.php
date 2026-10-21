<?php

/*
| Subscriptions and platform invoices (Step 7.4): the trial ends into a paid period with its invoice, periods renew, invoices are paid,
| voided or fall overdue (suspending the tenant until paid), and cancelling closes the tenant at the end of its period.
*/
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Actions\CancelSubscription;
use Modules\Platform\Actions\ChangeSubscription;
use Modules\Platform\Actions\RecordPlatformPayment;
use Modules\Platform\Actions\ResumeSubscription;
use Modules\Platform\Actions\RunBilling;
use Modules\Platform\Actions\StartSubscription;
use Modules\Platform\Actions\StartTrial;
use Modules\Platform\Actions\SuspendTenant;
use Modules\Platform\Actions\VoidPlatformInvoice;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Enums\PlatformInvoiceStatus;
use Modules\Platform\Enums\PlatformPaymentMethod;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Models\Subscription;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;

require_once __DIR__.'/../Support/platform-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    travelTo(CarbonImmutable::parse('2026-10-01 10:00'));
    $tenant = pfSetup();
    $tenant->forceFill(['status' => TenantStatus::Trial, 'trial_ends_at' => now()->addDays(14)])->save();
    app(StartTrial::class)->handle($tenant->fresh(), pfPlan('professional'), BillingPeriod::Monthly);
});

afterEach(fn () => travelBack());

function sbSubscription(): Subscription
{
    return Subscription::currentFor(tenant('sunrise')->id) ?? throw new RuntimeException('No subscription');
}

/** @return list<string> */
function sbInvoices(): array
{
    return PlatformInvoice::query()->orderBy('id')->get()->map(fn (PlatformInvoice $invoice): string => $invoice->number.' '.$invoice->amount.' '.$invoice->period_start?->toDateString().'..'.$invoice->period_end?->toDateString().' '.$invoice->status->value)->all();
}

it('ends the trial into a paid month with its invoice, and renews each month', function (): void {
    expect(app(RunBilling::class)->handle()['started'])->toBe(0)->and(sbInvoices())->toBe([]);

    // The trial ends at 10:00 on the 15th; the run at 00:30 leaves it for the next one.
    travelTo(CarbonImmutable::parse('2026-10-15 00:30'));
    expect(app(RunBilling::class)->handle()['started'])->toBe(0);

    travelTo(CarbonImmutable::parse('2026-10-15 12:00'));
    expect(app(RunBilling::class)->handle())->toMatchArray(['started' => 1, 'failed' => 0]);

    $subscription = sbSubscription();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)->and($subscription->current_period_end?->toDateString())->toBe('2026-11-14')
        ->and(tenant('sunrise')->status)->toBe(TenantStatus::Active)->and(tenant('sunrise')->trial_ends_at)->toBeNull()
        ->and(sbInvoices())->toBe(['INV-2026-00001 149.00 2026-10-15..2026-11-14 open'])
        ->and(PlatformInvoice::query()->sole()->due_on->toDateString())->toBe('2026-10-22');

    // Running again the same day does nothing more.
    expect(app(RunBilling::class)->handle())->toMatchArray(['started' => 0, 'renewed' => 0]);

    app(RecordPlatformPayment::class)->handle(PlatformInvoice::query()->sole(), '149.00', PlatformPaymentMethod::BankTransfer, 'TT-1', CarbonImmutable::today());
    travelTo(CarbonImmutable::parse('2026-11-15 00:30'));
    app(RunBilling::class)->handle();

    expect(sbInvoices())->toBe(['INV-2026-00001 149.00 2026-10-15..2026-11-14 paid', 'INV-2026-00002 149.00 2026-11-15..2026-12-14 open'])
        ->and(sbSubscription()->current_period_start?->toDateString())->toBe('2026-11-15');
});

it('catches up every missed period, and bills a yearly plan for a year', function (): void {
    app(StartSubscription::class)->handle(tenant('sunrise'), pfPlan('starter'), BillingPeriod::Yearly);

    expect(sbInvoices())->toBe(['INV-2026-00001 490.00 2026-10-01..2027-09-30 open'])->and(tenant('sunrise')->plan_id)->toBe(pfPlan('starter')->id);

    app(RecordPlatformPayment::class)->handle(PlatformInvoice::query()->sole(), '490', PlatformPaymentMethod::Card, null, CarbonImmutable::today());
    travelTo(CarbonImmutable::parse('2028-10-02 00:30'));
    app(RunBilling::class)->handle();

    expect(collect(sbInvoices())->map(fn (string $line): string => substr($line, 15, 29))->all())->toBe(['490.00 2026-10-01..2027-09-30', '490.00 2027-10-01..2028-09-30', '490.00 2028-10-01..2029-09-30']);
});

it('suspends a tenant whose invoice is overdue, and reactivates it when paid', function (): void {
    app(StartSubscription::class)->handle(tenant('sunrise'));
    $invoice = PlatformInvoice::query()->sole();

    travelTo(CarbonImmutable::parse('2026-10-08 12:00'));
    expect(app(RunBilling::class)->handle()['suspended'])->toBe(0);

    travelTo(CarbonImmutable::parse('2026-10-09 00:30'));
    expect(app(RunBilling::class)->handle()['suspended'])->toBe(1)
        ->and(tenant('sunrise')->status)->toBe(TenantStatus::Suspended)->and(tenant('sunrise')->suspended_for)->toBe('unpaid');
    get(tenantUrl('sunrise', '/'))->assertForbidden();

    app(RecordPlatformPayment::class)->handle($invoice, '100.00', PlatformPaymentMethod::BankTransfer, 'part', CarbonImmutable::today());
    expect(tenant('sunrise')->status)->toBe(TenantStatus::Suspended)->and($invoice->fresh()?->balance())->toBe('49.00');

    app(RecordPlatformPayment::class)->handle($invoice->fresh(), '49.00', PlatformPaymentMethod::BankTransfer, 'rest', CarbonImmutable::today());
    expect(tenant('sunrise')->status)->toBe(TenantStatus::Active)->and(tenant('sunrise')->suspended_for)->toBeNull()->and($invoice->fresh()?->status)->toBe(PlatformInvoiceStatus::Paid);
});

it('keeps a suspension by an admin when an invoice is paid', function (): void {
    app(StartSubscription::class)->handle(tenant('sunrise'));
    app(SuspendTenant::class)->handle(tenant('sunrise'), 'Abuse');
    app(RecordPlatformPayment::class)->handle(PlatformInvoice::query()->sole(), '149', PlatformPaymentMethod::BankTransfer, null, CarbonImmutable::today());

    expect(tenant('sunrise')->status)->toBe(TenantStatus::Suspended);
});

it('refuses a payment above the balance, in the future, or on a closed invoice, and voids only an unpaid invoice', function (): void {
    app(StartSubscription::class)->handle(tenant('sunrise'));
    $invoice = PlatformInvoice::query()->sole();
    $pay = fn (string $amount, ?CarbonImmutable $on = null) => app(RecordPlatformPayment::class)->handle($invoice->fresh(), $amount, PlatformPaymentMethod::Cash, null, $on ?? CarbonImmutable::today());

    expect(fn () => $pay('149.01'))->toThrow(PlatformRuleViolated::class, 'at most the balance')
        ->and(fn () => $pay('0'))->toThrow(PlatformRuleViolated::class)
        ->and(fn () => $pay('10', CarbonImmutable::tomorrow()))->toThrow(PlatformRuleViolated::class, 'future');

    $pay('10');
    expect(fn () => app(VoidPlatformInvoice::class)->handle($invoice->fresh(), 'mistake'))->toThrow(PlatformRuleViolated::class, 'nothing paid');

    $other = PlatformInvoice::factory()->create(['tenant_id' => tenant('sunrise')->id]);
    expect(fn () => app(VoidPlatformInvoice::class)->handle($other, ' '))->toThrow(PlatformRuleViolated::class, 'Say why');
    app(VoidPlatformInvoice::class)->handle($other, 'Billed twice');

    expect($other->fresh()?->status)->toBe(PlatformInvoiceStatus::Void)->and(fn () => app(RecordPlatformPayment::class)->handle($other->fresh(), '1', PlatformPaymentMethod::Cash, null, CarbonImmutable::today()))
        ->toThrow(PlatformRuleViolated::class, 'takes no payments');
});

it('cancels at the end of the trial or the period, can take it back, and closes the tenant', function (): void {
    expect(fn () => app(CancelSubscription::class)->handle(sbSubscription(), ''))->toThrow(PlatformRuleViolated::class);

    app(CancelSubscription::class)->handle(sbSubscription(), 'Too expensive');
    app(ResumeSubscription::class)->handle(sbSubscription());
    expect(sbSubscription()->cancel_at_period_end)->toBeFalse()->and(fn () => app(ResumeSubscription::class)->handle(sbSubscription()))->toThrow(PlatformRuleViolated::class);

    app(CancelSubscription::class)->handle(sbSubscription(), 'Too expensive');
    travelTo(CarbonImmutable::parse('2026-10-15 12:00'));
    expect(app(RunBilling::class)->handle()['cancelled'])->toBe(1);

    expect(tenant('sunrise')->status)->toBe(TenantStatus::Cancelled)->and(tenant('sunrise')->cancelled_at)->not->toBeNull()
        ->and(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::Cancelled)->and(sbInvoices())->toBe([]);
    get(tenantUrl('sunrise', '/'))->assertNotFound();
});

it('changes the plan at once and bills the new price from the next period', function (): void {
    app(StartSubscription::class)->handle(tenant('sunrise'));
    $over = app(ChangeSubscription::class)->handle(sbSubscription(), pfPlan('starter'), BillingPeriod::Monthly);

    expect($over)->toBe([])->and(tenant('sunrise')->plan_id)->toBe(pfPlan('starter')->id);

    travelTo(CarbonImmutable::parse('2026-11-01 00:30'));
    app(RunBilling::class)->handle();

    expect(PlatformInvoice::query()->orderBy('id')->pluck('amount')->all())->toBe(['149.00', '49.00']);
});

it('starts, changes and cancels a subscription, and records a payment, from the console', function (): void {
    $admin = pfAdmin();
    $tenant = tenant('sunrise');

    get(centralUrl('/platform/tenants/'.$tenant->id))->assertOk()->assertSee('End the trial and start billing');
    post(centralUrl('/platform/tenants/'.$tenant->id.'/subscription'), ['plan_id' => pfPlan('enterprise')->id, 'billing_period' => 'yearly'])->assertRedirect()->assertSessionHas('success');

    $invoice = PlatformInvoice::query()->sole();
    expect($invoice->amount)->toBe('3990.00')->and(sbSubscription()->status)->toBe(SubscriptionStatus::Active);

    put(centralUrl('/platform/tenants/'.$tenant->id.'/subscription'), ['plan_id' => pfPlan('professional')->id, 'billing_period' => 'monthly'])->assertSessionHas('success');
    get(centralUrl('/platform/invoices?overdue=1'))->assertOk()->assertDontSee($invoice->number);
    get(centralUrl('/platform/invoices'))->assertOk()->assertSee($invoice->number);
    get(centralUrl('/platform/invoices/'.$invoice->id))->assertOk()->assertSee('Record a payment');
    get(centralUrl('/platform/invoices/'.$invoice->id.'/pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    post(centralUrl('/platform/invoices/'.$invoice->id.'/payments'), ['amount' => '3990.00', 'method' => 'bank_transfer', 'reference' => 'TT-77', 'paid_on' => today()->toDateString()])->assertSessionHas('success');
    expect($invoice->fresh()?->status)->toBe(PlatformInvoiceStatus::Paid)->and($invoice->payments()->sole()->recorded_by)->toBe($admin->id);

    post(centralUrl('/platform/tenants/'.$tenant->id.'/subscription/cancel'), ['reason' => 'Closing down', 'immediately' => '1'])->assertSessionHas('success');
    expect(tenant('sunrise')->status)->toBe(TenantStatus::Cancelled)
        ->and(pfAudit('subscription.'))->toBe(['subscription.started', 'subscription.changed', 'subscription.cancel_requested']);
});

it('closes a tenant with no subscription from the console', function (): void {
    pfAdmin();
    $tenant = Tenant::factory()->create(['slug' => 'plain']);

    post(centralUrl('/platform/tenants/'.$tenant->id.'/close'), ['reason' => ''])->assertSessionHasErrors('reason');
    post(centralUrl('/platform/tenants/'.$tenant->id.'/close'), ['reason' => 'Asked to leave'])->assertSessionHas('success');

    expect($tenant->fresh()?->status)->toBe(TenantStatus::Cancelled);
});

it('keeps the console invoices and subscription actions for platform admins only', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));

    get(centralUrl('/platform/invoices'))->assertRedirect(centralUrl('/platform/login'));
    post(centralUrl('/platform/tenants/'.tenant('sunrise')->id.'/subscription'), ['plan_id' => pfPlan('starter')->id, 'billing_period' => 'monthly'])->assertRedirect(centralUrl('/platform/login'));
});
