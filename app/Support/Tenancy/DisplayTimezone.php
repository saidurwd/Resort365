<?php

namespace App\Support\Tenancy;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The timezone times are shown in (ARCHITECTURE §12 rule 9: stored in UTC, displayed in the property's
 * timezone). It is the timezone of the property a screen is about: the one set with use() (a POS terminal's
 * or kitchen display's property), else the current property of the signed-in person (PropertyContext), else
 * the application's. Scoped to the request or job. Show a stored timestamp with Carbon's
 * `inPropertyTime()` macro in views (registered in AppServiceProvider) or format() in PHP code; never change the application's timezone, as
 * that would shift what is already stored.
 */
class DisplayTimezone
{
    private ?string $forced = null;

    /** @var array<int, string> property id => timezone */
    private array $known = [];

    public function __construct(
        private readonly PropertyContext $properties,
    ) {}

    /**
     * Show times in a property's timezone (by id) for the rest of the request.
     */
    public function use(int $propertyId): void
    {
        $this->forced = $this->of($propertyId);
    }

    public function name(): string
    {
        if ($this->forced !== null) {
            return $this->forced;
        }

        $current = $this->properties->currentId();

        return $current !== null ? $this->of($current) : (string) config('app.timezone');
    }

    public function of(int $propertyId): string
    {
        if (! isset($this->known[$propertyId])) {
            try {
                $name = DB::table('properties')->where('id', $propertyId)->value('timezone');
            } catch (Throwable) {
                $name = null;
            }

            $this->known[$propertyId] = is_string($name) && $name !== '' ? $name : (string) config('app.timezone');
        }

        return $this->known[$propertyId];
    }

    public function convert(CarbonInterface $time): CarbonInterface
    {
        return $time->copy()->setTimezone($this->name());
    }

    /**
     * A stored timestamp as text in the display timezone (empty when there is none), for PHP code: tables,
     * messages. Views use the `inPropertyTime()` macro.
     */
    public function format(?CarbonInterface $time, string $format = 'd M Y H:i'): string
    {
        return $time instanceof CarbonInterface ? $this->convert($time)->format($format) : '';
    }
}
