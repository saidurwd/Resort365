<?php

namespace Modules\Core\Services;

use App\Support\Attachments\Attachments;
use Illuminate\Support\Facades\URL;
use Modules\Core\Contracts\PublicPhotos;
use Modules\Core\Models\Media;

/**
 * Signed links to gallery photos for the public website (Step 8.2a). The link names the photo and the size; the signature is what
 * makes it public, so changing the id or the size breaks it.
 */
class PublicPhotoLinks implements PublicPhotos
{
    public function url(int $mediaId, string $size = self::WEB): ?string
    {
        $media = Media::query()->whereKey($mediaId)->where('collection_name', Attachments::PHOTOS)->first();

        return $media instanceof Media ? $this->link($media, $size) : null;
    }

    public function many(array $mediaIds): array
    {
        if ($mediaIds === []) {
            return [];
        }

        $found = Media::query()->whereIn('id', $mediaIds)->where('collection_name', Attachments::PHOTOS)->get()->keyBy('id');
        $photos = [];

        foreach ($mediaIds as $id) {
            $media = $found->get($id);

            if ($media instanceof Media) {
                $photos[] = ['id' => $media->id, 'thumb' => $this->link($media, self::THUMB), 'web' => $this->link($media, self::WEB), 'alt' => (string) $media->getCustomProperty('caption', $media->name)];
            }
        }

        return $photos;
    }

    private function link(Media $media, string $size): string
    {
        return URL::signedRoute('core.photos.show', ['media' => $media->id, 'size' => in_array($size, [self::THUMB, self::WEB], true) ? $size : self::WEB]);
    }
}
