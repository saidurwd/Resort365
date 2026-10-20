<?php

namespace Modules\Reports\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Reports\Services\DashboardData;
use Modules\Reports\Services\ReportFilters;

/**
 * The management dashboard (Step 7.1): each part appears only for the people who may see its figures.
 */
class DashboardController extends Controller
{
    public function show(Request $request, ReportFilters $filters, DashboardData $data): View
    {
        $user = $request->user();
        $scope = $filters->dashboardScope((string) $request->query('property', ''));
        $hasProperty = $scope['ids'] !== [];
        $can = fn (string $permission): bool => $user?->can($permission) ?? false;
        $showStats = $hasProperty && $can('reports.dashboard.view');
        $stats = $showStats ? $data->statistics($scope['ids']) : null;
        $desk = $hasProperty && $can('frontoffice.desk.view') ? $data->frontDesk($scope['ids'], $scope['today']) : null;
        $cash = $hasProperty && $can('accounting.bank.view') ? $data->cash($scope['today']) : null;

        return view('reports::dashboard.show', [
            'stats' => $stats, 'desk' => $desk, 'cash' => $cash, 'scope' => $scope, 'currency' => $scope['currency'], 'fnb' => $can('restaurant.report.view'), 'consolidated' => count($scope['ids']) > 1,
            'canConsolidate' => count($filters->dashboardScope('all')['ids']) > 1, 'all' => $request->query('property') === 'all',
        ]);
    }
}
