<?php

namespace Modules\Procurement\Http\Controllers\Concerns;

use App\Support\Tenancy\PropertyContext;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Procurement documents belong to the current property.
 */
trait ProcurementScreen
{
    protected function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    protected function propertyName(): ?string
    {
        return app(PropertyDirectory::class)->find($this->propertyId())->name ?? null;
    }

    protected function userId(): int
    {
        return (int) auth()->id();
    }
}
