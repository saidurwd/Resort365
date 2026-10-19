<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\TaxSlab;
use Modules\Payroll\Models\TaxTable;

/**
 * Creates or changes an income tax table with its slabs (Step 6.4). The slabs are bands above the tax-free amount: every band
 * has a width and a rate except the last, which has no top. Taxpayer categories and tax areas are data, so any country's
 * rules fit.
 */
class SaveTaxTable extends Action
{
    /**
     * @param  array<string, mixed>  $data  name, tax_year, effective_from, exemption_fraction, exemption_cap, is_active; thresholds and minimum_tax: lists of code, label, amount; slabs: list of width, rate
     *
     * @throws PayrollRuleViolated
     */
    public function handle(?TaxTable $table, array $data): TaxTable
    {
        $thresholds = $this->codes((array) ($data['thresholds'] ?? []), __('tax-free amount'));
        $minimum = $this->codes((array) ($data['minimum_tax'] ?? []), __('minimum tax'), allowEmpty: true);
        $slabs = $this->slabs((array) ($data['slabs'] ?? []));
        $fraction = trim((string) ($data['exemption_fraction'] ?? '0'));

        if (preg_match('/^(\d+(\.\d+)?|\d+\/\d+)$/', $fraction) !== 1) {
            throw new PayrollRuleViolated(__('The exemption is a decimal such as 0.25 or a fraction such as 1/3.'));
        }

        return $this->transaction(function () use ($table, $data, $thresholds, $minimum, $slabs, $fraction): TaxTable {
            $table ??= new TaxTable;
            $table->fill([
                'name' => trim((string) $data['name']), 'tax_year' => trim((string) $data['tax_year']), 'effective_from' => (string) $data['effective_from'], 'thresholds' => $thresholds, 'minimum_tax' => $minimum,
                'exemption_fraction' => $fraction, 'exemption_cap' => (string) ($data['exemption_cap'] ?? '0'), 'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();
            $table->slabs()->delete();

            foreach ($slabs as $order => $slab) {
                TaxSlab::query()->create($slab + ['tax_table_id' => $table->id, 'sort_order' => $order + 1]);
            }

            return $table->load('slabs');
        });
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<array{code: string, label: string, amount: string}>
     *
     * @throws PayrollRuleViolated
     */
    private function codes(array $rows, string $what, bool $allowEmpty = false): array
    {
        $found = [];
        $seen = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $code = strtolower(trim((string) ($row['code'] ?? '')));

            if ($code === '' && ($row['amount'] ?? '') === '') {
                continue;
            }

            if (preg_match('/^[a-z][a-z0-9_]{0,29}$/', $code) !== 1 || isset($seen[$code])) {
                throw new PayrollRuleViolated(__('Each :what needs its own code of lower-case letters, digits and underscores.', ['what' => $what]));
            }

            if (! is_numeric($row['amount'] ?? null) || (float) $row['amount'] < 0) {
                throw new PayrollRuleViolated(__('Enter an amount for every :what.', ['what' => $what]));
            }

            $seen[$code] = true;
            $found[] = ['code' => $code, 'label' => trim((string) ($row['label'] ?? '')) ?: ucfirst(str_replace('_', ' ', $code)), 'amount' => number_format((float) $row['amount'], 2, '.', '')];
        }

        if ($found === [] && ! $allowEmpty) {
            throw new PayrollRuleViolated(__('Add at least one :what.', ['what' => $what]));
        }

        return $found;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<array{width: string|null, rate: string}>
     *
     * @throws PayrollRuleViolated
     */
    private function slabs(array $rows): array
    {
        $slabs = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            if (($row['rate'] ?? '') === '' && ($row['width'] ?? '') === '') {
                continue;
            }

            if (! is_numeric($row['rate'] ?? null) || (float) $row['rate'] < 0 || (float) $row['rate'] > 100) {
                throw new PayrollRuleViolated(__('A slab rate is between 0 and 100 percent.'));
            }

            $width = ($row['width'] ?? '') === '' ? null : $row['width'];

            if ($width !== null && (! is_numeric($width) || (float) $width <= 0)) {
                throw new PayrollRuleViolated(__('A slab width is more than zero (leave it empty for the last slab).'));
            }

            $slabs[] = ['width' => $width !== null ? number_format((float) $width, 2, '.', '') : null, 'rate' => number_format((float) $row['rate'], 2, '.', '')];
        }

        if ($slabs === []) {
            throw new PayrollRuleViolated(__('Add at least one slab.'));
        }

        foreach (array_slice($slabs, 0, -1) as $slab) {
            if ($slab['width'] === null) {
                throw new PayrollRuleViolated(__('Only the last slab can be without a width.'));
            }
        }

        if ($slabs[count($slabs) - 1]['width'] !== null) {
            throw new PayrollRuleViolated(__('The last slab must have no width: it covers everything above the others.'));
        }

        return $slabs;
    }
}
