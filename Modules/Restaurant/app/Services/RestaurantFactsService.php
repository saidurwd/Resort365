<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Modules\Core\Contracts\Settings;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Contracts\RestaurantFacts;
use Modules\Restaurant\DTOs\BillFact;
use Modules\Restaurant\DTOs\SessionFact;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\PosSessionStatus;
use Modules\Restaurant\Enums\RevenueClass;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosBillLine;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;

class RestaurantFactsService implements RestaurantFacts
{
    public function __construct(
        private readonly Settings $settings,
        private readonly PropertyDirectory $properties,
    ) {}

    public function bill(int $billId): ?BillFact
    {
        $bill = PosBill::query()->with(['lines.line', 'outlet'])->find($billId);

        if (! $bill instanceof PosBill || $bill->settled_at === null || ! in_array($bill->status, [BillStatus::Settled, BillStatus::Voided], true)) {
            return null;
        }

        $serviceCode = (string) $this->settings->get('restaurant.service_charge_code', $bill->property_id);
        $taxes = [];

        foreach ($bill->tax_breakdown ?? [] as $row) {
            if ($row['code'] !== $serviceCode) {
                $taxes[$row['name']] = (string) BigDecimal::of($taxes[$row['name']] ?? '0')->plus($row['amount'])->toScale(2);
            }
        }

        $payments = [];
        $tips = BigDecimal::zero();

        foreach (PosPayment::query()->where('pos_bill_id', $bill->id)->whereNull('refund_of_id')->orderBy('id')->get() as $payment) {
            $payments[$payment->method->value] = (string) BigDecimal::of($payments[$payment->method->value] ?? '0')->plus($payment->amount)->plus($payment->tip)->toScale(2);
            $tips = $tips->plus($payment->tip);
        }

        return new BillFact(
            $bill->id, $bill->bill_no, $bill->property_id, $bill->outlet_id, $bill->outlet->name, $bill->business_date->toDateString(), $this->revenue($bill),
            (string) BigDecimal::of($bill->service_charge)->toScale(2), $taxes, (string) BigDecimal::of($bill->grand_total)->toScale(2), (string) $tips->toScale(2),
            $payments, $bill->is_complimentary, $bill->status === BillStatus::Voided,
        );
    }

    public function session(int $sessionId): ?SessionFact
    {
        $session = PosSession::query()->with('outlet')->find($sessionId);

        return $session instanceof PosSession && $session->status === PosSessionStatus::Closed
            ? new SessionFact($session->id, $session->property_id, $session->outlet_id, $session->outlet->name, $session->business_date->toDateString(), (string) BigDecimal::of($session->cash_variance ?? '0')->toScale(2))
            : null;
    }

    public function outlets(): array
    {
        $names = [];

        foreach ($this->properties->all() as $property) {
            $names[$property->id] = $property->name;
        }

        return Outlet::query()->orderBy('property_id')->orderBy('sort_order')->get()
            ->map(fn (Outlet $outlet): array => ['id' => $outlet->id, 'name' => $outlet->name, 'property' => $names[$outlet->property_id] ?? ''])->all();
    }

    public function history(): array
    {
        return [
            'bills' => PosBill::query()->whereNotNull('settled_at')->whereIn('status', [BillStatus::Settled->value, BillStatus::Voided->value])->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'sessions' => PosSession::query()->where('status', PosSessionStatus::Closed->value)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ];
    }

    /**
     * @return array{food: string, beverage: string}
     */
    private function revenue(PosBill $bill): array
    {
        $categories = MenuCategory::query()->get(['id', 'parent_id', 'revenue_class'])->keyBy('id');
        $classes = MenuItem::query()->whereIn('id', $bill->lines->pluck('line.menu_item_id')->filter()->unique())->pluck('menu_category_id', 'id');
        $totals = [RevenueClass::Food->value => BigDecimal::zero(), RevenueClass::Beverage->value => BigDecimal::zero()];

        foreach ($bill->lines as $line) {
            /** @var PosBillLine $line */
            $class = $this->classOf($categories->all(), (int) ($classes[$line->line->menu_item_id] ?? 0));
            $totals[$class->value] = $totals[$class->value]->plus($line->amount)->minus($line->discount);
        }

        return ['food' => (string) $totals['food']->toScale(2), 'beverage' => (string) $totals['beverage']->toScale(2)];
    }

    /**
     * @param  array<int, MenuCategory>  $categories
     */
    private function classOf(array $categories, int $categoryId): RevenueClass
    {
        for ($id = $categoryId, $guard = 0; $id !== 0 && isset($categories[$id]) && $guard < 20; $guard++) {
            $category = $categories[$id];

            if ($category->revenue_class instanceof RevenueClass) {
                return $category->revenue_class;
            }

            $id = (int) $category->parent_id;
        }

        return RevenueClass::Food;
    }
}
