<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Enums\Allergen;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\DietaryTag;
use Modules\Restaurant\Enums\MenuItemKind;

/**
 * Reads a menu CSV (ARCHITECTURE §5.10.2 import) without the database. Columns: code, name, description,
 * category ("Food > Mains"), course, kind, tax_category (code), dietary_tags, allergens and variants
 * (each list separated by "|"), name_<lang> / description_<lang> for other menu languages, and one
 * price:<OUTLET CODE> column per outlet: one price, or one per variant ("350|600"); empty = not sold
 * there. Every row is checked; the caller imports nothing when there are errors.
 */
class MenuCsv
{
    public const array COLUMNS = ['code', 'name', 'description', 'category', 'course', 'kind', 'tax_category', 'dietary_tags', 'allergens', 'variants'];

    /**
     * @param  list<string>  $outletCodes  the property's outlet codes (for price: columns)
     * @param  list<string>  $languages  menu languages (name_<lang> columns)
     * @return array{rows: list<array{line: int, code: string, name: array<string, string>, description: array<string, string>, category: list<string>, course: string,
     *     kind: string, tax_category: string|null, dietary_tags: list<string>, allergens: list<string>, variants: list<string>, prices: array<string, list<string>>}>, errors: list<string>}
     */
    public function parse(string $contents, array $outletCodes, array $languages): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', ltrim($contents, "\u{FEFF}")) ?: [], fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            return ['rows' => [], 'errors' => [__('The file is empty.')]];
        }

        $header = array_map(fn (?string $cell): string => strtolower(trim((string) $cell)), str_getcsv($lines[0], ',', '"', ''));
        $errors = [];

        foreach (['code', 'name', 'category', 'course'] as $required) {
            if (! in_array($required, $header, true)) {
                $errors[] = __('Column ":column" is missing.', ['column' => $required]);
            }
        }

        foreach ($header as $column) {
            if (str_starts_with($column, 'price:') && ! in_array(strtoupper(substr($column, 6)), array_map(strtoupper(...), $outletCodes), true)) {
                $errors[] = __('Column ":column": no outlet has that code.', ['column' => $column]);
            }
        }

        if ($errors !== []) {
            return ['rows' => [], 'errors' => $errors];
        }

        $rows = [];
        $codes = [];

        foreach (array_slice($lines, 1) as $index => $line) {
            $number = $index + 2;
            $cells = str_getcsv($line, ',', '"', '');
            $row = [];

            foreach ($header as $i => $column) {
                $row[$column] = trim($cells[$i] ?? '');
            }

            [$parsed, $rowErrors] = $this->row($row, $number, $languages);

            if (isset($codes[strtoupper($parsed['code'])])) {
                $rowErrors[] = __('Line :line: code :code appears twice.', ['line' => $number, 'code' => $parsed['code']]);
            }

            $codes[strtoupper($parsed['code'])] = true;
            $errors = [...$errors, ...$rowErrors];
            $rows[] = $parsed;
        }

        return ['rows' => $errors === [] ? $rows : [], 'errors' => $errors];
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $languages
     * @return array{array{line: int, code: string, name: array<string, string>, description: array<string, string>, category: list<string>, course: string, kind: string,
     *     tax_category: string|null, dietary_tags: list<string>, allergens: list<string>, variants: list<string>, prices: array<string, list<string>>}, list<string>}
     */
    private function row(array $row, int $line, array $languages): array
    {
        $errors = [];
        $list = fn (string $value): array => array_values(array_filter(array_map(trim(...), explode('|', $value)), fn (string $part): bool => $part !== ''));
        $fail = function (string $message) use (&$errors, $line): void {
            $errors[] = __('Line :line: :message', ['line' => $line, 'message' => $message]);
        };

        $code = strtoupper($row['code'] ?? '');
        $name = ['en' => $row['name'] ?? ''];
        $description = array_filter(['en' => $row['description'] ?? '']);

        foreach ($languages as $language) {
            if ($language !== 'en') {
                $name[$language] = $row['name_'.$language] ?? '';
                $description[$language] = $row['description_'.$language] ?? '';
            }
        }

        $kind = $row['kind'] ?? '' ?: MenuItemKind::Dish->value;
        $variants = $list($row['variants'] ?? '');
        $tags = $list(strtolower($row['dietary_tags'] ?? ''));
        $allergens = $list(strtolower($row['allergens'] ?? ''));
        $prices = [];

        if ($code === '' || preg_match('/^[A-Z0-9_-]{1,20}$/', $code) !== 1) {
            $fail(__('the code must be 1–20 letters, digits, - or _.'));
        }

        if (trim($name['en']) === '') {
            $fail(__('the name is missing.'));
        }

        if (trim($row['category'] ?? '') === '') {
            $fail(__('the category is missing.'));
        }

        if (Course::tryFrom(strtolower($row['course'] ?? '')) === null) {
            $fail(__('course ":value" is not one of :list.', ['value' => $row['course'] ?? '', 'list' => implode(', ', Course::values())]));
        }

        if (MenuItemKind::tryFrom(strtolower($kind)) === null) {
            $fail(__('kind ":value" is not one of :list.', ['value' => $kind, 'list' => implode(', ', MenuItemKind::values())]));
        }

        foreach (array_diff($tags, DietaryTag::values()) as $tag) {
            $fail(__('dietary tag ":value" is unknown.', ['value' => $tag]));
        }

        foreach (array_diff($allergens, Allergen::values()) as $allergen) {
            $fail(__('allergen ":value" is unknown.', ['value' => $allergen]));
        }

        foreach ($row as $column => $value) {
            if (! str_starts_with($column, 'price:') || $value === '') {
                continue;
            }

            $amounts = array_map(trim(...), explode('|', $value));
            $outlet = strtoupper(substr($column, 6));

            if (count($amounts) !== max(1, count($variants))) {
                $fail(__(':outlet needs :count price(s), one per variant.', ['outlet' => $outlet, 'count' => max(1, count($variants))]));
            } elseif (array_filter($amounts, fn (string $amount): bool => preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount) !== 1) !== []) {
                $fail(__(':outlet price ":value" is not an amount.', ['outlet' => $outlet, 'value' => $value]));
            }

            $prices[$outlet] = $amounts;
        }

        return [[
            'line' => $line, 'code' => $code, 'name' => $name, 'description' => $description,
            'category' => array_values(array_filter(array_map(trim(...), explode('>', $row['category'] ?? '')))),
            'course' => strtolower($row['course'] ?? ''), 'kind' => strtolower($kind),
            'tax_category' => ($row['tax_category'] ?? '') !== '' ? strtoupper($row['tax_category']) : null,
            'dietary_tags' => $tags, 'allergens' => $allergens, 'variants' => $variants, 'prices' => $prices,
        ], $errors];
    }
}
