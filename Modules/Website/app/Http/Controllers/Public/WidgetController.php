<?php

namespace Modules\Website\Http\Controllers\Public;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Website\Models\WebsiteSite;

/**
 * The embeddable search box (Step 8.2b) at `/resort/{site}/widget`: a resort's own website shows it in an iframe; a search opens the
 * results on this site in the whole window. Only this page may be framed, and only by the origins in the site's `embed_origins`.
 */
class WidgetController extends Controller
{
    public function __invoke(Request $request): View
    {
        $site = $request->attributes->get('website.site');
        abort_unless($site instanceof WebsiteSite, 404);

        $request->attributes->set(SecurityHeaders::FRAME_ANCESTORS, $site->embedOrigins());

        return view('website::public.widget');
    }
}
