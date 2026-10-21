<?php

namespace Modules\Platform\Http\Controllers;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\TenantDataExports;
use Modules\Core\DTOs\TenantExportSummary;
use Modules\Platform\Services\PlatformAudit;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform console → a tenant's data export (Step 7.4): for a company that is leaving or has left (also when cancelled), made by
 * Core in the background and downloaded here. Both are in the console's audit log.
 */
class TenantExportController extends Controller
{
    public function store(Request $request, Tenant $tenant, TenantContext $context, TenantDataExports $exports, PlatformAudit $audit): RedirectResponse
    {
        $admin = $request->user('platform');

        try {
            $context->run($tenant, fn (): TenantExportSummary => $exports->request(null, __(':name (platform)', ['name' => (string) $admin?->getAttribute('name')])));
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        $audit->log($admin, 'tenant.export_requested', __('Data export of :tenant requested', ['tenant' => $tenant->name]), $tenant->id);

        return to_route('platform.tenants.show', $tenant)->with('success', __('The export is being prepared; reload this page in a few minutes.'));
    }

    public function download(Request $request, Tenant $tenant, int $export, TenantContext $context, TenantDataExports $exports, PlatformAudit $audit): StreamedResponse
    {
        $file = $context->run($tenant, fn (): ?array => $exports->file($export)) ?? abort(404);
        $audit->log($request->user('platform'), 'tenant.export_downloaded', __('Data export of :tenant downloaded', ['tenant' => $tenant->name]), $tenant->id, ['export' => $export]);

        return Storage::disk($file['disk'])->download($file['path'], $tenant->slug.'-'.$file['name']);
    }
}
