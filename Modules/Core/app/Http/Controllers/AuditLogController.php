<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Services\AuditLogTable;

class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('core::audit.index', ['columns' => AuditLogTable::columns()]);
    }

    public function data(Request $request, AuditLogTable $table): JsonResponse
    {
        $event = $request->query('event');

        return $table->toJson(is_string($event) && $event !== '' ? $event : null);
    }
}
