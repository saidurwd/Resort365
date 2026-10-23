<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * The REST API's documents on the central domain (Step 8.1): the reader at /docs/api and the OpenAPI 3.1 file at /docs/api.json,
 * generated from the code by Scramble. Scramble reads the FormRequests' rules, and tenant-scoped rules (TenantRule) need a tenant,
 * so the document is made inside a placeholder tenant that is never saved or queried.
 */
class ApiDocumentsController
{
    public function __construct(private readonly CacheableGenerator $generator, private readonly TenantContext $tenants) {}

    public function show(): View
    {
        return $this->inDocumentTenant(function (): View {
            $config = Scramble::getGeneratorConfig(Scramble::DEFAULT_API);
            $result = $this->generator->generate($config);

            return view($config->renderer()->view, ['spec' => $result->spec($config->openApiVersion()), 'config' => $config, 'result' => $result]);
        });
    }

    public function json(): JsonResponse
    {
        return $this->inDocumentTenant(fn (): JsonResponse => response()->json(($this->generator)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API)), options: JSON_PRETTY_PRINT));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inDocumentTenant(callable $callback): mixed
    {
        $placeholder = (new Tenant)->forceFill(['id' => 0, 'slug' => 'tenant', 'name' => 'API documents']);

        return $this->tenants->run($placeholder, fn (): mixed => $callback());
    }
}
