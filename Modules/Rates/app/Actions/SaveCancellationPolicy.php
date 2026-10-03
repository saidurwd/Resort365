<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Arr;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\CancellationPolicyRule;

/**
 * Creates or updates a cancellation policy with its tiers (validated by
 * SaveCancellationPolicyRequest). Tier changes are recorded in the policy's audit trail;
 * a new default replaces the property's previous default.
 */
class SaveCancellationPolicy extends Action
{
    /**
     * @param  array<string, mixed>  $data  fields plus rules: list of {days_before_from, days_before_to, charge_type, charge_value}
     */
    public function handle(?CancellationPolicy $policy, array $data): CancellationPolicy
    {
        return $this->transaction(function () use ($policy, $data): CancellationPolicy {
            $policy ??= new CancellationPolicy;
            $policy->fill(Arr::except($data, 'rules'))->save();

            $old = $this->describe($policy);
            $policy->rules()->delete();

            foreach ((array) ($data['rules'] ?? []) as $rule) {
                $policy->rules()->create([
                    'property_id' => $policy->property_id,
                    'days_before_from' => (int) $rule['days_before_from'],
                    'days_before_to' => isset($rule['days_before_to']) && $rule['days_before_to'] !== '' ? (int) $rule['days_before_to'] : null,
                    'charge_type' => $rule['charge_type'],
                    'charge_value' => $rule['charge_value'],
                ]);
            }

            $new = $this->describe($policy);

            if ($old !== $new) {
                activity()->performedOn($policy)->event('updated')
                    ->withProperties(['old' => ['tiers' => $old], 'attributes' => ['tiers' => $new]])->log('updated');
            }

            if ($policy->is_default) {
                CancellationPolicy::query()->where('property_id', $policy->property_id)->whereKeyNot($policy->id)->where('is_default', true)
                    ->get()->each(fn (CancellationPolicy $other) => $other->update(['is_default' => false]));
            }

            return $policy->load('rules');
        });
    }

    private function describe(CancellationPolicy $policy): string
    {
        return $policy->rules()->get()->map(fn (CancellationPolicyRule $rule): string => $rule->windowLabel().': '.$rule->charge_type->describe($rule->charge_value))->implode('; ');
    }
}
