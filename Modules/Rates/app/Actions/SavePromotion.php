<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Models\Promotion;

/**
 * Creates or updates a promotion (validated by SavePromotionRequest).
 */
class SavePromotion extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Promotion $promotion, array $data): Promotion
    {
        $promotion ??= new Promotion;
        $promotion->fill($data)->save();

        return $promotion;
    }
}
