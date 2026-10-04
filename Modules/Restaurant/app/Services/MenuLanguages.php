<?php

namespace Modules\Restaurant\Services;

use Modules\Core\Contracts\Settings;

/**
 * The languages menus are written in (setting restaurant.menu_languages, e.g. "en,bn"); English first.
 */
class MenuLanguages
{
    public const array NAMES = ['en' => 'English', 'bn' => 'Bangla', 'hi' => 'Hindi', 'ar' => 'Arabic', 'zh' => 'Chinese', 'fr' => 'French', 'de' => 'German'];

    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * @return array<string, string> code => name
     */
    public function all(): array
    {
        $codes = array_unique(['en', ...array_map(fn (string $code): string => strtolower(trim($code)), explode(',', (string) $this->settings->get('restaurant.menu_languages')))]);

        return collect($codes)->filter(fn (string $code): bool => $code !== '')->mapWithKeys(fn (string $code): array => [$code => self::NAMES[$code] ?? strtoupper($code)])->all();
    }
}
