<?php

namespace Modules\Billing\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Database\Factories\FolioRoutingRuleFactory;
use Modules\Billing\Enums\ChargeCategory;

/**
 * Sends a reservation's charges of one category to one of its folios ("company pays room").
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $reservation_id
 * @property ChargeCategory $category
 * @property int $target_folio_id
 */
#[UseFactory(FolioRoutingRuleFactory::class)]
#[Fillable(['property_id', 'reservation_id', 'category', 'target_folio_id'])]
class FolioRoutingRule extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<FolioRoutingRuleFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['category' => ChargeCategory::class];
    }
}
