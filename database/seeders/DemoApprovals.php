<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\RequestRefund;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\DTOs\NewRefund;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\RefundKind;
use Modules\Core\Actions\SeedApprovalWorkflows;
use Modules\Guest\Contracts\GuestLookup;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Models\Reservation;

/**
 * Approval workflows (Step 5.1): every tenant gets the default workflow of each document type, and at Rodela a
 * guest who paid 12,000 up front cancelled: the front desk's refund request waits for the General Manager.
 */
final class DemoApprovals
{
    public static function seed(Tenant $tenant, int $propertyId, ?string $domain = null): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $domain): void {
            app(SeedApprovalWorkflows::class)->handle();

            if ($domain === null) {
                return;
            }

            $plan = collect(app(RateLookup::class)->ratePlans($propertyId))->firstWhere('code', 'BB');
            $room = collect(app(InventoryCatalog::class)->rooms($propertyId))->firstWhere('number', '701');
            $guest = app(GuestLookup::class)->search('ayesha.siddique@example.com')[0] ?? null;
            $requester = collect(app(UserDirectory::class)->all())->firstWhere('email', 'frontdesk@'.$domain);

            if ($plan === null || $room === null || $guest === null || $requester === null || Reservation::query()->where('special_requests', 'Cancelled: refund waits for approval')->exists()) {
                return;
            }

            $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
            $booking = CreateReservation::make()->handle(new NewReservation($propertyId, $today->addDays(60), $today->addDays(63),
                [new BookingItem(ItemType::Room, $room->id, $plan->id, 2)], $guest->id, ReservationSource::Online, depositPercent: '100', specialRequests: 'Cancelled: refund waits for approval'));
            RecordPayment::make()->handle(new NewPayment($booking->id, PaymentMethod::Card, '12000.00', 'VISA-5512'));
            CancelReservation::make()->handle($booking->fresh() ?? $booking, 'Guest could not travel');
            RequestRefund::make()->handle(new NewRefund($booking->id, RefundKind::Cancellation, PaymentMethod::Card, '12000.00', 'Guest could not travel', null, null, $requester->id));
        });
    }
}
