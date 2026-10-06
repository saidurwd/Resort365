<?php

namespace Modules\Reservation\Jobs;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\DisplayTimezone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Models\Reservation;
use Throwable;

/**
 * The hold-expiry job (ARCHITECTURE §6.5 rule 4), scheduled every five minutes: in every tenant
 * that may use the app, tentative bookings past their deposit due time, with auto-cancel on and
 * the deposit not covered, are cancelled free of charge and their rooms released. It runs in the
 * scheduler process (not queued); one booking that fails does not stop the others. The guest
 * and the staff member who made the booking are told by SendBookingNotifications.
 */
class ExpireTentativeHolds
{
    use Dispatchable;

    /**
     * @return int how many bookings were cancelled
     */
    public function handle(TenantContext $context, CancelReservation $cancel): int
    {
        $cancelled = 0;
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));

        foreach (Tenant::query()->whereIn('status', $statuses)->orderBy('id')->get() as $tenant) {
            $cancelled += $context->run($tenant, fn (): int => $this->expire($cancel));
        }

        return $cancelled;
    }

    /**
     * The expired holds of one property in the current tenant (night audit step 4).
     */
    public function forProperty(int $propertyId, CancelReservation $cancel): int
    {
        return $this->expire($cancel, $propertyId);
    }

    private function expire(CancelReservation $cancel, ?int $propertyId = null): int
    {
        $cancelled = 0;
        $expired = Reservation::query()
            ->when($propertyId !== null, fn ($query) => $query->where('property_id', $propertyId))
            ->where('status', ReservationStatus::Tentative->value)
            ->where('auto_cancel_unpaid', true)
            ->where('deposit_due_at', '<=', now())
            ->whereColumn('amount_paid', '<', 'deposit_required')
            ->orderBy('deposit_due_at')
            ->get();

        foreach ($expired as $reservation) {
            try {
                $cancel->handle($reservation, __('Deposit not paid by :time.', ['time' => $reservation->deposit_due_at?->setTimezone(app(DisplayTimezone::class)->of($reservation->property_id))->format('d M Y H:i')]), expired: true);
                $cancelled++;
            } catch (ReservationNotChangeable) {
                // Paid or changed in the meantime.
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $cancelled;
    }
}
