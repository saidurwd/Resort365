<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;
use InvalidArgumentException;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;

/**
 * A setting a module registers (ARCHITECTURE §5.1): key `module.name`, type, default and scope.
 * Labels and help are translation keys.
 */
final readonly class SettingDefinition extends Data
{
    /**
     * @param  array<string, string>  $options  value => label, for SettingType::Select
     * @param  list<mixed>  $rules  extra validation rules
     */
    public function __construct(
        public string $key,
        public string $label,
        public SettingType $type,
        public mixed $default = null,
        public SettingScope $scope = SettingScope::Tenant,
        public string $group = 'General',
        public ?string $help = null,
        public array $options = [],
        public array $rules = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9]*\.[a-z][a-z0-9_]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Setting [{$key}] must be named module.name.");
        }
    }

    /**
     * @return list<mixed>
     */
    public function validationRules(): array
    {
        return ['nullable', ...$this->type->rules($this->options), ...$this->rules];
    }
}
