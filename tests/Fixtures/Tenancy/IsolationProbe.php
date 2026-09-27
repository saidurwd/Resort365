<?php

namespace Tests\Fixtures\Tenancy;

use App\Support\Attachments\HasAttachments;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * Sample tenant-owned model for the isolation harness (also attachable, for the attachment tests).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 */
#[UseFactory(IsolationProbeFactory::class)]
#[Fillable(['code', 'name'])]
class IsolationProbe extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasAttachments;

    /** @use HasFactory<IsolationProbeFactory> */
    use HasFactory;
}
