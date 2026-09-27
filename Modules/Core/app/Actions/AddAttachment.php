<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use App\Support\Attachments\Attachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Stores an uploaded file on a record (validated by UploadAttachmentRequest).
 */
class AddAttachment extends Action
{
    /**
     * @throws ValidationException when the file's content is not an allowed type
     */
    public function handle(HasMedia $subject, UploadedFile $file, ?Model $uploader): Media
    {
        try {
            $media = $subject->addMedia($file)
                ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ->withCustomProperties([
                    'uploaded_by' => $uploader?->getKey(),
                    'uploaded_by_name' => $uploader?->getAttribute('name'),
                ])
                ->toMediaCollection(Attachments::COLLECTION);
        } catch (FileUnacceptableForCollection) {
            // The content does not match an allowed type (e.g. a program renamed to .pdf).
            throw ValidationException::withMessages(['file' => __('This file type is not allowed.')]);
        }

        if ($subject instanceof Model) {
            activity()->performedOn($subject)->event('updated')->log('Attachment "'.$media->file_name.'" added');
        }

        return $media;
    }
}
