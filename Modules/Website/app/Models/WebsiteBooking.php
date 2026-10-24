<?php

namespace Modules\Website\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Website\Database\Factories\WebsiteBookingFactory;
use Modules\Website\Enums\PayChoice;

/**
 * A booking made on the public website (Step 8.2b): the reservation (Reservation module, by id and code), what the guest chose to pay
 * now and how much that is, and where it came from.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $website_site_id
 * @property int $reservation_id
 * @property string $reservation_code
 * @property PayChoice $pay_choice
 * @property string $amount_due_now
 * @property string $currency_code
 * @property string $email
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
#[UseFactory(WebsiteBookingFactory::class)]
#[Fillable(['property_id', 'website_site_id', 'reservation_id', 'reservation_code', 'pay_choice', 'amount_due_now', 'currency_code', 'email', 'ip_address'])]
class WebsiteBooking extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<WebsiteBookingFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['pay_choice' => PayChoice::class, 'amount_due_now' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<WebsiteSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'website_site_id');
    }
}
