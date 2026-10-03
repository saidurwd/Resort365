<?php

namespace Modules\Core\Http\Requests;

use App\Support\Attachments\Attachments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Authorization happens in the controller against the attached record's policy.
 * `collection` picks the attachments list (default) or the record's photo gallery.
 */
class UploadAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $types = $this->collection() === Attachments::PHOTOS ? Attachments::PHOTO_EXTENSIONS : (array) config('attachments.extensions');

        return [
            'collection' => ['nullable', Rule::in(Attachments::UPLOAD_COLLECTIONS)],
            'file' => ['required', File::types($types)->max((int) config('attachments.max_kilobytes'))],
        ];
    }

    public function collection(): string
    {
        $collection = $this->input('collection');

        return is_string($collection) && $collection !== '' ? $collection : Attachments::COLLECTION;
    }
}
