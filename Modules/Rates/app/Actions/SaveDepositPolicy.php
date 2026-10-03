<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Models\DepositPolicy;

/**
 * Creates or updates a deposit policy (validated by SaveDepositPolicyRequest). A new default
 * replaces the property's previous default.
 */
class SaveDepositPolicy extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?DepositPolicy $policy, array $data): DepositPolicy
    {
        return $this->transaction(function () use ($policy, $data): DepositPolicy {
            $policy ??= new DepositPolicy;
            $policy->fill($data)->save();

            if ($policy->is_default) {
                DepositPolicy::query()->where('property_id', $policy->property_id)->whereKeyNot($policy->id)->where('is_default', true)
                    ->get()->each(fn (DepositPolicy $other) => $other->update(['is_default' => false]));
            }

            return $policy;
        });
    }
}
