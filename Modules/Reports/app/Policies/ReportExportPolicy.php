<?php

namespace Modules\Reports\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Reports\Models\ReportExport;

/**
 * Report files: a person sees and downloads the files they asked for, and only while they hold reports.report.export.
 */
class ReportExportPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('reports.report.export');
    }

    public function view(Authenticatable&Authorizable $user, ReportExport $export): bool
    {
        return $user->can('reports.report.export') && $export->user_id === $user->getAuthIdentifier();
    }
}
