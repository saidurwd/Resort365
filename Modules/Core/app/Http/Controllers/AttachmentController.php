<?php

namespace Modules\Core\Http\Controllers;

use App\Support\Attachments\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Actions\AddAttachment;
use Modules\Core\Actions\DeleteAttachment;
use Modules\Core\Http\Requests\UploadAttachmentRequest;
use Modules\Core\Models\Media;
use Spatie\MediaLibrary\HasMedia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files are only ever served here, after checking the attached record's policy
 * (`view` to download, `update` to upload or delete). Media is tenant-scoped, so
 * another tenant's file is a 404.
 */
class AttachmentController extends Controller
{
    public function store(UploadAttachmentRequest $request, string $type, string $id, AddAttachment $addAttachment): RedirectResponse
    {
        $subject = $this->subject($type, $id);
        Gate::authorize('update', $subject);

        $user = $request->user();
        $media = $addAttachment->handle($subject, $request->file('file'), $user instanceof Model ? $user : null);

        return back()->with('success', __('":name" attached.', ['name' => $media->file_name]));
    }

    public function show(Media $media): StreamedResponse
    {
        Gate::authorize('view', $media->model);

        return $media->toInlineResponse(request());
    }

    public function destroy(Media $media, DeleteAttachment $deleteAttachment): RedirectResponse
    {
        Gate::authorize('update', $media->model);

        $deleteAttachment->handle($media);

        return back()->with('success', __('Attachment deleted.'));
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
