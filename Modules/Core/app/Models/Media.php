<?php

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Database\Factories\MediaFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * An attached file of the current tenant. Served only through Core's authorized attachment routes.
 */
#[UseFactory(MediaFactory::class)]
class Media extends SpatieMedia
{
    use BelongsToTenant;

    /** @use HasFactory<MediaFactory> */
    use HasFactory;
}
