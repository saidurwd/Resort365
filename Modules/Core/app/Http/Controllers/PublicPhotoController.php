<?php

namespace Modules\Core\Http\Controllers;

use App\Support\Attachments\Attachments;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * A gallery photo through a signed public link (Step 8.2a, PublicPhotos). The `signed` route middleware checks the link; this serves
 * only `photos`-collection files of the current tenant, at the asked size when it has been made, with a long browser cache.
 */
class PublicPhotoController extends Controller
{
    public function __invoke(int $media, string $size): Response
    {
        $photo = Media::query()->whereKey($media)->where('collection_name', Attachments::PHOTOS)->firstOrFail();
        $headers = ['Cache-Control' => 'public, max-age=604800, immutable'];

        if (in_array($size, [Attachments::THUMB, Attachments::WEB], true) && $photo->hasGeneratedConversion($size)) {
            return Storage::disk($photo->conversions_disk ?: $photo->disk)->response($photo->getPathRelativeToRoot($size), headers: $headers);
        }

        return Storage::disk($photo->disk)->response($photo->getPathRelativeToRoot(), headers: $headers);
    }
}
