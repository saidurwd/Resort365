<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\AnnouncementSummary;

/**
 * The platform's announcements that apply to the current tenant now (Step 7.3). Platform implements it; without Platform there
 * are none.
 */
interface Announcements
{
    /**
     * @return list<AnnouncementSummary>
     */
    public function active(): array;
}
