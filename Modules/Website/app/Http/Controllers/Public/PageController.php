<?php

namespace Modules\Website\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Services\SitePages;

/**
 * The public website's pages (Step 8.2a), at `/resort/{site}/…`. No sign-in: the `website.site` middleware finds the site and the
 * language; everything shown comes from SitePages.
 */
class PageController extends Controller
{
    public function __construct(private readonly SitePages $pages) {}

    public function home(Request $request): View
    {
        $site = $this->site($request);
        $units = $this->pages->units($site);

        return $this->page('home', $site, [
            'heroPhotos' => array_slice($this->pages->sitePhotos($site), 0, 3),
            'featured' => $this->pages->featured($units),
            'offers' => array_slice($this->pages->offers($site, $this->currency($site)), 0, 3),
        ]);
    }

    public function rooms(Request $request): View
    {
        $site = $this->site($request);

        return $this->page('rooms', $site, ['units' => $this->pages->units($site)]);
    }

    public function room(Request $request, string $site, string $kind, int $id): View
    {
        $current = $this->site($request);
        $unit = $this->pages->find($current, $kind, $id) ?? abort(404);

        return $this->page('room', $current, ['unit' => $unit]);
    }

    public function dining(Request $request): View
    {
        $site = $this->site($request);

        return $this->page('dining', $site, ['outlets' => $this->pages->dining($site)]);
    }

    public function offers(Request $request): View
    {
        $site = $this->site($request);

        return $this->page('offers', $site, ['offers' => $this->pages->offers($site, $this->currency($site))]);
    }

    public function gallery(Request $request): View
    {
        $site = $this->site($request);

        return $this->page('gallery', $site, ['photos' => $this->pages->gallery($site, $this->pages->units($site))]);
    }

    public function contact(Request $request): View
    {
        return $this->page('contact', $this->site($request));
    }

    public function policies(Request $request): View
    {
        $site = $this->site($request);

        return $this->page('policies', $site, ['policies' => $this->pages->policies($site, $this->currency($site))]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, WebsiteSite $site, array $data = []): View
    {
        $property = $this->pages->property($site);

        return view('website::public.'.$view, [...$data, 'property' => $property, 'currency' => $property->currencyCode ?? 'BDT', 'hasDining' => $this->pages->dining($site) !== []]);
    }

    private function site(Request $request): WebsiteSite
    {
        $site = $request->attributes->get('website.site');

        return $site instanceof WebsiteSite ? $site : abort(404);
    }

    private function currency(WebsiteSite $site): string
    {
        return $this->pages->property($site)->currencyCode ?? 'BDT';
    }
}
