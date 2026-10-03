<?php

namespace App\Support\Attachments;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Adds a photo gallery (the "photos" collection, JPEG/PNG/WebP, with a thumbnail) to an
 * attachable model. Same requirements as HasAttachments: HasMedia, a morph-map alias and a policy.
 *
 * Show it with <x-photo-gallery :subject="$model" />. Photos are private and served through
 * Core's authorized routes. TODO(step-8.2): public photo URLs for the booking website.
 */
trait HasPhotos
{
    use HasAttachments {
        HasAttachments::registerMediaCollections as registerAttachmentsCollection;
    }

    public function registerMediaCollections(): void
    {
        $this->registerAttachmentsCollection();
        $this->addMediaCollection(Attachments::PHOTOS)->acceptsMimeTypes(Attachments::PHOTO_MIME_TYPES);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(Attachments::THUMB)
            ->performOnCollections(Attachments::PHOTOS)
            ->nonQueued()
            ->fit(Fit::Crop, 480, 320);
    }
}
