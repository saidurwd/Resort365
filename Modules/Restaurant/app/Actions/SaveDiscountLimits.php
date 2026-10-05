<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Restaurant\Models\DiscountLimit;

/**
 * Saves each role's largest discount % without a manager (ARCHITECTURE §5.10.7); an empty or zero limit
 * means the role may not discount on its own.
 */
class SaveDiscountLimits extends Action
{
    /**
     * @param  array<int, string|null>  $limits  role id => max %
     */
    public function handle(array $limits): void
    {
        $this->transaction(function () use ($limits): void {
            foreach ($limits as $roleId => $percent) {
                $limit = DiscountLimit::query()->where('role_id', $roleId)->first();

                if ($percent === null || $percent === '' || BigDecimal::of($percent)->isZero()) {
                    $limit?->delete();

                    continue;
                }

                ($limit ?? new DiscountLimit(['role_id' => $roleId]))->fill(['max_percent' => (string) BigDecimal::of($percent)->toScale(2)])->save();
            }
        });
    }
}
