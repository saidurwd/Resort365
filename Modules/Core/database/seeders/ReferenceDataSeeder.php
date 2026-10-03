<?php

namespace Modules\Core\Database\Seeders;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Timezones;

/**
 * ISO 3166 countries, ISO 4217 currencies and IANA timezones (central tables). Idempotent.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $countries = [];
        foreach (Countries::getNames('en') as $code => $name) {
            $countries[] = [
                'code' => $code,
                'iso3' => Countries::getAlpha3Code($code),
                'numeric_code' => Countries::getNumericCode($code),
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('countries')->upsert($countries, ['code'], ['iso3', 'numeric_code', 'name', 'updated_at']);

        $currencies = [];
        foreach (Currencies::getNames('en') as $code => $name) {
            $currencies[] = [
                'code' => $code,
                'name' => $name,
                'symbol' => mb_substr(Currencies::getSymbol($code, 'en'), 0, 10),
                'decimals' => Currencies::getFractionDigits($code),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('currencies')->upsert($currencies, ['code'], ['name', 'symbol', 'decimals', 'updated_at']);

        $timezones = [];
        foreach (DateTimeZone::listIdentifiers() as $name) {
            try {
                $country = Timezones::getCountryCode($name);
            } catch (MissingResourceException) {
                $country = null;
            }

            $timezones[] = [
                'name' => $name,
                'country_code' => $country,
                'utc_offset' => new DateTimeImmutable('now', new DateTimeZone($name))->format('P'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('timezones')->upsert($timezones, ['name'], ['country_code', 'utc_offset', 'updated_at']);

        foreach (['countries', 'currencies', 'timezones'] as $list) {
            Cache::forget('core.reference.'.$list);
        }
    }
}
