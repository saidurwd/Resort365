<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A customer company (central, not tenant-scoped). Served from {slug}.{central domain}.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $email
 * @property TenantStatus $status
 * @property int|null $plan_id
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $suspended_at
 * @property string|null $suspended_for why: `unpaid` (an overdue platform invoice) or `admin`
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $purged_at its data deleted after the retention period (Step 7.5)
 */
#[UseFactory(TenantFactory::class)]
#[Fillable([
    'slug',
    'name',
    'email',
    'status',
    'plan_id',
    'trial_ends_at',
    'suspended_at',
    'suspended_for',
    'cancelled_at',
    'purged_at',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    /**
     * The tenant's own host, e.g. rodela.resort365.test.
     */
    public function domain(): string
    {
        return $this->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * Absolute URL on the tenant's subdomain.
     */
    public function url(string $path = '/'): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$this->domain().'/'.ltrim($path, '/');
    }
}
