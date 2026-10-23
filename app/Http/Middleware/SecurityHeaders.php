<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (ARCHITECTURE §9.1, Step 7.5).
 *
 * The Content-Security-Policy allows scripts from this site, with a per-request nonce for the few inline scripts (render them with
 * `nonce="{{ Vite::cspNonce() }}"`), the Vite dev server when it runs, and the Reverb WebSocket. Alpine's standard build evaluates
 * its expressions, so scripts also need 'unsafe-eval'; styles need 'unsafe-inline' for style attributes. Forms may post to the
 * central domain and its tenant subdomains (sign-up redirects to the new one). Horizon and Pulse (platform admins only) and the API
 * documents (Step 8.1, a static page from a CDN) render their own inline scripts, so they get the other headers but no CSP. HSTS is sent in production only.
 */
class SecurityHeaders
{
    /** Paths whose pages bring their own inline scripts. */
    private const array WITHOUT_CSP = ['platform/horizon*', 'platform/pulse*', 'livewire*', 'docs/api*'];

    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $request->is(...self::WITHOUT_CSP)) {
            $response->headers->set('Content-Security-Policy', $this->policy());
        }

        return $response;
    }

    private function policy(): string
    {
        $central = (string) config('tenancy.central_domain');
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $sites = "'self' {$scheme}://{$central} {$scheme}://*.{$central}";
        $dev = $this->viteDevServer();
        $sockets = $this->reverbOrigins();

        // Directives with nothing optional to add keep single spaces.
        return implode('; ', array_map(fn (string $directive): string => trim((string) preg_replace('/\s+/', ' ', $directive)), [
            "default-src 'self'",
            "script-src 'self' 'nonce-".Vite::cspNonce()."' 'unsafe-eval' {$dev}",
            "style-src 'self' 'unsafe-inline' {$dev}",
            "img-src 'self' data: blob:",
            "font-src 'self' data: {$dev}",
            "connect-src 'self' {$sockets} {$dev} ".str_replace('http', 'ws', $dev),
            "frame-src 'self'",
            "frame-ancestors 'self'",
            "form-action {$sites}",
            "base-uri 'self'",
            "object-src 'none'",
        ]));
    }

    /** The Vite dev server's origin while `npm run dev` runs (its URL is in public/hot), else nothing. */
    private function viteDevServer(): string
    {
        $hot = Vite::hotFile();

        return is_file($hot) ? rtrim(trim((string) file_get_contents($hot)), '/') : '';
    }

    /** Where the browser opens the Reverb WebSocket (Step 3.5). */
    private function reverbOrigins(): string
    {
        $host = config('broadcasting.connections.reverb.options.host');

        if (! is_string($host) || $host === '') {
            return '';
        }

        $port = (string) config('broadcasting.connections.reverb.options.port');
        $secure = config('broadcasting.connections.reverb.options.scheme') === 'https';

        return ($secure ? 'wss' : 'ws').'://'.$host.($port !== '' ? ':'.$port : '');
    }
}
