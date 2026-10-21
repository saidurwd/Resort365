<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\Events\PropertyCreated;
use Illuminate\Http\UploadedFile;
use Modules\Core\Contracts\PlanLimits;
use Modules\Property\Models\Property;

/**
 * Creates or updates a property (validated by SavePropertyRequest). A new property starts
 * with today's business date in its timezone; night audit moves it on (Step 2.6). A new property must fit the plan (Step 7.3).
 */
class SaveProperty extends Action
{
    public function __construct(private readonly PlanLimits $limits) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Property $property, array $data, ?UploadedFile $logo = null): Property
    {
        $creating = ! $property instanceof Property;

        if ($creating) {
            $this->limits->ensureWithin('max_properties', Property::query()->count() + 1);
        }

        $property = $this->transaction(function () use ($property, $data): Property {
            $property ??= new Property(['business_date' => now((string) $data['timezone'])->toDateString()]);
            $property->fill($data)->save();

            return $property;
        });

        if ($logo instanceof UploadedFile) {
            $property->addMedia($logo)->toMediaCollection(Property::LOGO);
        }

        if ($creating) {
            PropertyCreated::dispatch($property->tenant_id, $property->id);
        }

        return $property;
    }
}
