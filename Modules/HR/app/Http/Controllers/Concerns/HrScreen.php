<?php

namespace Modules\HR\Http\Controllers\Concerns;

use App\Support\Tenancy\PropertyContext;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Rosters, attendance and holidays belong to the current property.
 */
trait HrScreen
{
    protected function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    protected function propertyName(): ?string
    {
        return app(PropertyDirectory::class)->find($this->propertyId())->name ?? null;
    }

    /** The current property's business date. */
    protected function today(): string
    {
        return app(PropertyDirectory::class)->find($this->propertyId())->businessDate ?? now()->toDateString();
    }
}
