<?php

namespace Modules\Core\Http\Controllers;

use App\Support\Attachments\Attachments;
use App\Support\Attachments\HasAttachments;
use App\Support\Attachments\HasPhotos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Actions\AddAttachment;
use Modules\Core\Actions\DeleteAttachment;
use Modules\Core\Http\Requests\UploadAttachmentRequest;
use Modules\Core\Models\Media;
use Spatie\MediaLibrary\HasMedia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files are only ever served here, after checking the attached record's policy
 * (`view` to download, `update` to upload or delete). A policy with a `viewAttachments`
 * method guards downloads more strictly than the record itself (e.g. guest ID scans).
 * Media is tenant-scoped, so another tenant's file is a 404.
 */
class AttachmentController extends Controller
{
    public function store(UploadAttachmentRequest $request, string $type, string $id, AddAttachment $addAttachment): RedirectResponse
    {
        $subject = $this->subject($type, $id);
        Gate::authorize('update', $subject);

        $collection = $request->collection();
        abort_if($collection === Attachments::PHOTOS && ! in_array(HasPhotos::class, class_uses_recursive($subject), true), 404);

        $user = $request->user();
        $media = $addAttachment->handle($subject, $request->file('file'), $user instanceof Model ? $user : null, $collection);

        return back()->with('success', $collection === Attachments::PHOTOS
            ? __('Photo ":name" added.', ['name' => $media->file_name])
            : __('":name" attached.', ['name' => $media->file_name]));
    }

    /**
     * The file, or its gallery thumbnail with ?conversion=thumb.
     */
    public function show(Request $request, Media $media): StreamedResponse
    {
        Gate::authorize($this->viewAbility($media->model), $media->model);

        if ($request->query('conversion') === Attachments::THUMB && $media->hasGeneratedConversion(Attachments::THUMB)) {
            return Storage::disk($media->conversions_disk ?: $media->disk)->response($media->getPathRelativeToRoot(Attachments::THUMB));
        }

        return $media->toInlineResponse($request);
    }

    public function destroy(Media $media, DeleteAttachment $deleteAttachment): RedirectResponse
    {
        Gate::authorize('update', $media->model);

        $deleteAttachment->handle($media);

        return back()->with('success', __('Attachment deleted.'));
    }

    private function viewAbility(mixed $subject): string
    {
        $policy = is_object($subject) ? Gate::getPolicyFor($subject) : null;

        return is_object($policy) && method_exists($policy, 'viewAttachments') ? 'viewAttachments' : 'view';
    }

    /**
     * The attachable record from its morph alias and key (tenant-scoped lookup).
     *
     * @return Model&HasMedia
     */
    private function subject(string $type, string $id): Model
    {
        $class = Relation::getMorphedModel($type);

        abort_unless(is_string($class) && is_subclass_of($class, HasMedia::class) && in_array(HasAttachments::class, class_uses_recursive($class), true), 404);

        /** @var Model&HasMedia $subject */
        $subject = $class::query()->findOrFail($id);

        return $subject;
    }
}
