<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\Settings;
use Modules\Core\Enums\SettingScope;

/**
 * Saves the settings of one group at tenant level (or for one property), all or nothing.
 */
class SaveSettings extends Action
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array<string, mixed>  $input  setting key => value
     *
     * @throws ValidationException
     */
    public function handle(string $group, array $input, ?int $propertyId = null): void
    {
        $definitions = array_values(array_filter(
            $this->settings->grouped()[$group] ?? [],
            fn ($definition): bool => $propertyId === null || $definition->scope === SettingScope::Property,
        ));
        $rules = [];
        $values = [];

        foreach ($definitions as $definition) {
            $field = str_replace('.', '__', $definition->key);
            $rules[$field] = $definition->validationRules();
            $values[$field] = $input[$field] ?? null;
        }

        $attributes = [];
        foreach ($definitions as $definition) {
            $attributes[str_replace('.', '__', $definition->key)] = __($definition->label);
        }

        Validator::make($values, $rules, [], $attributes)->validate();

        $this->transaction(function () use ($definitions, $values, $propertyId): void {
            foreach ($definitions as $definition) {
                $this->settings->set($definition->key, $values[str_replace('.', '__', $definition->key)], $propertyId);
            }
        });
    }
}
