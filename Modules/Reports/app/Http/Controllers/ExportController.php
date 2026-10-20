<?php

namespace Modules\Reports\Http\Controllers;

use App\Support\Tenancy\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Reports\Enums\ExportStatus;
use Modules\Reports\Models\ReportExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports → My files: the report files a person asked for and their download (Step 7.1).
 */
class ExportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ReportExport::class);

        return view('reports::exports.index', ['exports' => ReportExport::query()->where('user_id', $request->user()?->id)->orderByDesc('id')->limit(100)->get()]);
    }

    public function download(ReportExport $export, TenantStorage $storage): StreamedResponse
    {
        Gate::authorize('view', $export);
        abort_unless($export->status === ExportStatus::Ready && $export->path !== null && ($export->expires_at === null || $export->expires_at->isFuture()), 404);
        $contents = $storage->get($export->path) ?? abort(404);

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $export->fileName(), ['Content-Type' => $export->format->mime()]);
    }
}
