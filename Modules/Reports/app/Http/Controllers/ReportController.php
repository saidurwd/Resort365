<?php

namespace Modules\Reports\Http\Controllers;

use App\Support\Reports\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Modules\Reports\Enums\ExportFormat;
use Modules\Reports\Enums\ExportStatus;
use Modules\Reports\Http\Requests\ReportRequest;
use Modules\Reports\Jobs\GenerateReportExport;
use Modules\Reports\Models\ReportExport;
use Modules\Reports\Services\ReportFilters;
use Modules\Reports\Services\ReportRegistry;
use Modules\Reports\Services\ReportRunner;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports → the hub, and each report of the framework with its filters and its CSV, Excel and PDF files (Step 7.1).
 */
class ReportController extends Controller
{
    /** Reports of other modules the hub links to: group, label, route, permission. */
    private const array LINKS = [
        ['Reservations', 'Booking sources', 'reservation.reports.sources', 'reservation.report.view'],
        ['Front office', 'Flash report', 'frontoffice.reports.flash', 'frontoffice.report.view'],
        ['Finance', 'Financial reports', 'accounting.reports.index', 'accounting.report.view'],
        ['Restaurant', 'Sales', 'restaurant.reports.sales', 'restaurant.report.view'],
        ['Restaurant', 'Food cost %', 'restaurant.reports.food-cost', 'restaurant.report.view'],
        ['Inventory', 'Stock valuation', 'inventory.reports.valuation', 'inventory.report.view'],
        ['Inventory', 'Consumption by department', 'inventory.reports.departments', 'inventory.report.view'],
        ['Procurement', 'Open orders', 'procurement.reports.open-orders', 'procurement.report.view'],
        ['Procurement', 'Payables aging', 'procurement.reports.vendor-aging', 'procurement.report.view'],
        ['HR', 'HR reports', 'hr.reports.index', 'hr.report.view'],
        ['Payroll', 'Payroll reports', 'payroll.reports.index', 'payroll.report.view'],
    ];

    public function index(ReportRegistry $registry, ReportRequest $request): View
    {
        $user = $request->user();
        $groups = [];

        foreach ($registry->all() as $definition) {
            if ($user?->can($definition->permission)) {
                $groups[$definition->group][] = $definition;
            }
        }

        $links = [];

        foreach (self::LINKS as [$group, $label, $route, $permission]) {
            if (Route::has($route) && $user?->can($permission)) {
                $links[$group][] = ['label' => $label, 'url' => route($route)];
            }
        }

        return view('reports::reports.index', ['groups' => $groups, 'links' => $links, 'canExport' => $user?->can('reports.report.export') ?? false]);
    }

    public function show(ReportRequest $request, string $key, ReportRegistry $registry, ReportFilters $filters, ReportRunner $runner): View|StreamedResponse|RedirectResponse
    {
        $definition = $registry->find($key) ?? abort(404);
        abort_unless($request->user()?->can($definition->permission), 403);
        $filter = $filters->make($definition, $request->validated());
        $export = $request->validated('export');

        if ($export === 'xlsx' || $export === 'pdf') {
            abort_unless($request->user()->can('reports.report.export'), 403);
            $file = ReportExport::query()->create([
                'user_id' => $request->user()->id, 'report_key' => $key, 'title' => $definition->title, 'format' => ExportFormat::from($export), 'filters' => $filter->toArray(), 'status' => ExportStatus::Queued,
            ]);
            GenerateReportExport::dispatch($file->id);

            return back()->with('success', __('We are making the :format file. You will get a notification when it is ready.', ['format' => ExportFormat::from($export)->label()]));
        }

        $table = $runner->run($key, $filter);

        if ($export === 'csv') {
            return CsvExport::response($table->toArray(), $key.'-'.$filter->from.'.csv');
        }

        $scope = $filters->scope($filter->consolidated() ? 'all' : '');

        return view('reports::reports.show', [
            'definition' => $definition, 'table' => $table->toArray(), 'filter' => $filter, 'canConsolidate' => count($filters->scope('all')['ids']) > 1, 'canExport' => $request->user()->can('reports.report.export'),
            'propertyNames' => array_values($scope['names']), 'input' => $request->validated(),
        ]);
    }
}
