<?php

namespace App\Http\Controllers;

use App\Support\UiKit\UiKitSamples;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Local-only showcase of the shared layouts and Blade components.
 */
class UiKitController extends Controller
{
    public function index(UiKitSamples $samples): View
    {
        return view('ui-kit.index', $samples->forIndex());
    }

    public function print(UiKitSamples $samples): View
    {
        return view('ui-kit.print', $samples->forPrint());
    }

    public function datatable(UiKitSamples $samples): JsonResponse
    {
        return $samples->reservationsTable();
    }
}
