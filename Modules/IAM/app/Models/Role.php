<?php

namespace Modules\IAM\Models;

use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\IAM\Database\Factories\RoleFactory;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A tenant's role. System roles are the defaults (DefaultRole): their name is the enum value,
 * they are read-only in the UI, and `permissions:sync` manages their permissions.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $guard_name
 * @property string|null $description
 * @property bool $is_system
 */
#[UseFactory(RoleFactory::class)]
#[Fillable(['name', 'guard_name', 'description', 'tenant_id'])]
class Role extends SpatieRole
{
    use BelongsToTenant;

    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function defaultRole(): ?DefaultRole
    {
        return $this->is_system ? DefaultRole::tryFrom($this->name) : null;
    }

    /**
     * Display name: the translated label for system roles, the name for custom roles.
     */
    public function label(): string
    {
        return $this->defaultRole()?->label() ?? $this->name;
    }
}
