<?php

namespace Modules\Website\Http\Middleware;

use App\Support\Tenancy\PropertyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Modules\Website\Models\WebsiteSite;
use Symfony\Component\HttpFoundation\Response;

/**
 * `website.site` on the public pages (Step 8.2a): finds the site of `/resort/{site}` and picks the language. A site that is not
 * published is a 404, except as a preview for staff with `website.content.view`. The pages are public, so a signed-in person's
 * property restriction does not apply to them. `?lang=bn` chooses the language and the session remembers it.
 */
class ResolveSite
{
    public const string SESSION_LOCALE = 'website.locale';

    public function __construct(private readonly PropertyContext $properties) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->properties->clear();

        $site = WebsiteSite::query()->where('slug', (string) $request->route('site'))->first();
        $preview = $site instanceof WebsiteSite && ! $site->is_published;

        if (! $site instanceof WebsiteSite || ($preview && ! ($request->user()?->can('website.content.view') ?? false))) {
            abort(404);
        }

        $locales = (array) config('website.locales');
        $asked = $request->query('lang');

        if (is_string($asked) && isset($locales[$asked]) && $request->hasSession()) {
            $request->session()->put(self::SESSION_LOCALE, $asked);
        }

        $locale = is_string($asked) && isset($locales[$asked]) ? $asked : ($request->hasSession() ? $request->session()->get(self::SESSION_LOCALE, 'en') : 'en');
        app()->setLocale(isset($locales[$locale]) ? (string) $locale : 'en');

        $request->attributes->set('website.site', $site);
        View::share(['site' => $site, 'preview' => $preview, 'locales' => $locales]);

        return $next($request);
    }
}
