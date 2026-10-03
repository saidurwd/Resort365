<?php

namespace Modules\Core\Contracts;

/**
 * Options for country, currency and timezone pickers (central ISO reference data).
 */
interface ReferenceData
{
    /**
     * @return array<string, string> ISO 3166 alpha-2 => name
     */
    public function countries(): array;

    /**
     * @return array<string, string> ISO 4217 code => "BDT — Bangladeshi Taka"
     */
    public function currencies(): array;

    /**
     * @return array<string, string> identifier => "Asia/Dhaka (UTC+06:00)"
     */
    public function timezones(): array;
}
