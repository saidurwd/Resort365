<?php

namespace App\Support\Attachments;

/**
 * Shared names for attachments (see HasAttachments) and photo galleries (see HasPhotos).
 */
final class Attachments
{
    /**
     * The media collection that holds a record's attachments.
     */
    public const string COLLECTION = 'attachments';

    /**
     * The media collection that holds a record's photo gallery.
     */
    public const string PHOTOS = 'photos';

    /**
     * The gallery thumbnail conversion.
     */
    public const string THUMB = 'thumb';

    /**
     * Upload collections a client may choose (UploadAttachmentRequest).
     */
    public const array UPLOAD_COLLECTIONS = [self::COLLECTION, self::PHOTOS];

    /**
     * File types accepted in a photo gallery.
     */
    public const array PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const array PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
}
