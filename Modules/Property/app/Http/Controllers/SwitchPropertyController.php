<?php

namespace Modules\Property\Http\Controllers;

use App\Http\Middleware\SetCurrentProperty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Property\Models\Property;

/**
 * Navbar property switcher. Route binding only finds properties the user may access.
 */
class SwitchPropertyController extends Controller
{
    public function __invoke(Request $request, Property $property): RedirectResponse
    {
        abort_unless($property->isActive(), 404);

        $request->session()->put(SetCurrentProperty::SESSION_KEY, $property->id);

        return redirect()->back(fallback: route('dashboard'))->with('info', __('You are now working in :name.', ['name' => $property->name]));
    }
}
