<?php

namespace App\Support\Attachments;

use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Makes a model attachable (ARCHITECTURE §5.1 "Attachments"). The model must also implement
 * Spatie\MediaLibrary\HasMedia, have a morph-map alias (Relation::morphMap) and a policy:
 * `view` allows downloading its files, `update` uploading and deleting them.
 *
 * Show them with <x-attachments :subject="$model" />.
 */
trait HasAttachments
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(Attachments::COLLECTION)
            ->acceptsMimeTypes((array) config('attachments.mime_types'));
    }
}
