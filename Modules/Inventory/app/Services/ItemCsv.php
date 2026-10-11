<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Throwable;

/**
 * Reads an item list CSV (Step 5.2): a header row naming the columns, comma, semicolon or tab separated.
 * Needed: sku, name, category (a path "Food > Dairy" is allowed) and base_unit (a unit code). Optional: barcode,
 * purchase_unit, issue_unit, reorder_level, reorder_qty, standard_cost, stockable, batches, expiry, description.
 * Yes/no columns take yes, no, 1, 0, true, false, y, n. Pure: the lines read and the problems, with line numbers.
 */
class ItemCsv
{
    private const array REQUIRED = ['sku', 'name', 'category', 'base_unit'];

    private const array ALIASES = [
        'sku' => ['sku', 'item code', 'code'], 'name' => ['name', 'item', 'item name', 'description of item'], 'category' => ['category', 'item category', 'group'],
        'base_unit' => ['base_unit', 'base unit', 'unit', 'uom'], 'barcode' => ['barcode', 'ean'], 'purchase_unit' => ['purchase_unit', 'purchase unit'], 'issue_unit' => ['issue_unit', 'issue unit'],
        'reorder_level' => ['reorder_level', 'reorder level', 'min'], 'reorder_qty' => ['reorder_qty', 'reorder qty', 'reorder quantity'], 'standard_cost' => ['standard_cost', 'standard cost', 'cost', 'price'],
        'stockable' => ['stockable', 'is_stockable'], 'batches' => ['batches', 'track_batches'], 'expiry' => ['expiry', 'track_expiry'], 'description' => ['notes', 'remarks'],
    ];

    /**
     * @return array{rows: list<array{sku: string, name: string, category: string, base_unit: string, barcode: string|null, purchase_unit: string|null, issue_unit: string|null, reorder_level: string, reorder_qty: string, standard_cost: string|null, stockable: bool, batches: bool, expiry: bool, description: string|null}>, errors: list<string>}
     */
    public function parse(string $csv): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($csv)) ?: [], fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            return ['rows' => [], 'errors' => [__('The file is empty.')]];
        }

        $separator = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : (substr_count($lines[0], "\t") > substr_count($lines[0], ',') ? "\t" : ',');
        $header = array_map(fn (?string $cell): string => mb_strtolower(trim((string) $cell, " \t\n\r\0\x0B\xEF\xBB\xBF\"")), str_getcsv(array_shift($lines), $separator, '"', ''));
        $columns = [];

        foreach (self::ALIASES as $field => $names) {
            foreach ($header as $index => $name) {
                if (in_array($name, $names, true) && ! isset($columns[$field])) {
                    $columns[$field] = $index;
                }
            }
        }

        $missing = array_values(array_filter(self::REQUIRED, fn (string $field): bool => ! isset($columns[$field])));

        if ($missing !== []) {
            return ['rows' => [], 'errors' => [__('The header row needs the columns :columns.', ['columns' => implode(', ', $missing)])]];
        }

        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($lines as $offset => $line) {
            $number = $offset + 2;
            $cells = array_map(fn (?string $cell): string => trim((string) $cell), str_getcsv($line, $separator, '"', ''));
            $get = fn (string $field): string => isset($columns[$field]) ? ($cells[$columns[$field]] ?? '') : '';

            foreach (self::REQUIRED as $field) {
                if ($get($field) === '') {
                    $errors[] = __('Line :n: :field is empty.', ['n' => $number, 'field' => $field]);

                    continue 2;
                }
            }

            if (isset($seen[mb_strtolower($get('sku'))])) {
                $errors[] = __('Line :n: the SKU :sku is in the file twice.', ['n' => $number, 'sku' => $get('sku')]);

                continue;
            }

            try {
                $reorderLevel = $this->number($get('reorder_level'), '0');
                $reorderQty = $this->number($get('reorder_qty'), '0');
                $cost = $get('standard_cost') === '' ? null : $this->number($get('standard_cost'), '0');
            } catch (Throwable) {
                $errors[] = __('Line :n: a quantity or cost is not a number.', ['n' => $number]);

                continue;
            }

            $seen[mb_strtolower($get('sku'))] = true;
            $rows[] = [
                'sku' => mb_substr($get('sku'), 0, 40), 'name' => mb_substr($get('name'), 0, 150), 'category' => $get('category'), 'base_unit' => $get('base_unit'),
                'barcode' => $get('barcode') !== '' ? mb_substr($get('barcode'), 0, 50) : null, 'purchase_unit' => $get('purchase_unit') !== '' ? $get('purchase_unit') : null,
                'issue_unit' => $get('issue_unit') !== '' ? $get('issue_unit') : null, 'reorder_level' => $reorderLevel, 'reorder_qty' => $reorderQty, 'standard_cost' => $cost,
                'stockable' => $this->flag($get('stockable'), true), 'batches' => $this->flag($get('batches'), false), 'expiry' => $this->flag($get('expiry'), false),
                'description' => $get('description') !== '' ? mb_substr($get('description'), 0, 500) : null,
            ];
        }

        if ($rows === [] && $errors === []) {
            $errors[] = __('The file has no items.');
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    private function number(string $value, string $default): string
    {
        $clean = str_replace([',', ' '], '', $value);

        return $clean === '' ? $default : (string) BigDecimal::of($clean)->toScale(4, RoundingMode::HalfUp);
    }

    private function flag(string $value, bool $default): bool
    {
        return match (mb_strtolower($value)) {
            '' => $default,
            '1', 'yes', 'y', 'true' => true,
            default => false,
        };
    }
}
