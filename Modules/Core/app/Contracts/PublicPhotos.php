<?php

namespace Modules\Core\Contracts;

/**
 * Public links to photos (Step 8.2a), for the booking website. Photos are private files; a link is a permanent signed URL that the
 * website makes for a photo it chose to show, and only a gallery photo (the `photos` collection) is ever served through it, never an
 * attachment or an ID scan.
 */
interface PublicPhotos
{
    public const string THUMB = 'thumb';

    public const string WEB = 'web';

    /**
     * The photo's public link at the size (`thumb` 480 × 320, `web` at most 1600 × 1200), or null when it is not a gallery photo.
     */
    public function url(int $mediaId, string $size = self::WEB): ?string;

    /**
     * Links for several photos, in the order given; ids that are not gallery photos are left out.
     *
     * @param  list<int>  $mediaIds
     * @return list<array{id: int, thumb: string, web: string, alt: string}>
     */
    public function many(array $mediaIds): array;
}
