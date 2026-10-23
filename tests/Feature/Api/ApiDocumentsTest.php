<?php

/*
| The API's OpenAPI document (Step 8.1): published on the central domain, generated from the code, and covering every /api/v1 route.
*/

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routes;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

it('publishes an OpenAPI document that covers every API route', function (): void {
    $document = getJson(centralUrl('/docs/api.json'))->assertOk()->json();

    expect($document['openapi'])->toStartWith('3.1')
        ->and($document['info']['title'])->toBe('Resort365 API')
        ->and($document['servers'][0]['url'])->toBe('http://{tenant}.'.config('tenancy.central_domain').'/api/v1')
        ->and($document['components']['securitySchemes'])->toHaveKey('http');

    $documented = [];
    foreach ($document['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            $documented[] = strtoupper($method).' /api/v1'.$path;
        }
    }

    $routes = collect(Routes::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (Route $route): array => array_map(fn (string $method): string => $method.' /'.$route->uri(), array_diff($route->methods(), ['HEAD'])))
        ->values()->all();

    expect($routes)->not->toBeEmpty()
        ->and(array_diff($routes, $documented))->toBe([])
        ->and($document['paths']['/reservation/reservations']['post']['responses'])->toHaveKeys(['201', '422']);
});

it('shows the documents to anyone, on the central domain only', function (): void {
    get(centralUrl('/docs/api'))->assertOk()->assertSee('Resort365 API')->assertHeaderMissing('Content-Security-Policy');
    get(tenantUrl('sunrise', '/docs/api'))->assertNotFound();
});
