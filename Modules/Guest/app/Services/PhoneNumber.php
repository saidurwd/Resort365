<?php

namespace Modules\Guest\Services;

/**
 * Normalises phone numbers to international format for storage and duplicate matching:
 * "01711-000000" → "+8801711000000" (with calling code 880), "0044 20 7946 0000" → "+442079460000".
 */
class PhoneNumber
{
    /**
     * @param  string  $callingCode  country calling code for local numbers, digits only (e.g. 880)
     */
    public function normalize(?string $phone, string $callingCode): ?string
    {
        $phone = trim((string) $phone);
        $digits = (string) preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        return match (true) {
            str_starts_with($phone, '+') => '+'.$digits,
            str_starts_with($digits, '00') => '+'.substr($digits, 2),
            str_starts_with($digits, '0') => '+'.$callingCode.substr($digits, 1),
            str_starts_with($digits, $callingCode) && strlen($digits) > 10 => '+'.$digits,
            default => '+'.$callingCode.$digits,
        };
    }
}
