<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\Events\PropertyCreated;
use Illuminate\Http\UploadedFile;
use Modules\Property\Models\Property;

/**
 * Creates or updates a property (validated by SavePropertyRequest). A new property starts
 * with today's business date in its timezone; night audit moves it on (Step 2.6).
 */
class SaveProperty extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Property $property, array $data, ?UploadedFile $logo = null): Property
    {
        $creating = ! $property instanceof Property;

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
