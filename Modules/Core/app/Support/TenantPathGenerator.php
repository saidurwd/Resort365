<?php

namespace Modules\Core\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Stores attachments under tenants/{tenant_id}/media/{media_id}/ (ARCHITECTURE §4.2, "Files").
 */
class TenantPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return 'tenants/'.$media->getAttribute('tenant_id').'/media/'.$media->getKey().'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive-images/';
    }
}
