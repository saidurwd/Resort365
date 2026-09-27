<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Media;

class DeleteAttachment extends Action
{
    public function handle(Media $media): void
    {
        $subject = $media->model;

        if ($subject instanceof Model) {
            activity()->performedOn($subject)->event('updated')->log('Attachment "'.$media->file_name.'" deleted');
        }

        $media->delete();
    }
}
