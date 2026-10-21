<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\Announcements;

class NoAnnouncements implements Announcements
{
    public function active(): array
    {
        return [];
    }
}
