<?php

namespace Modules\Core\Services;

use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalHandler;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\DTOs\ApprovalDocumentType;

class ApprovalRegistryService implements ApprovalRegistry
{
    /** @var array<string, ApprovalDocumentType> */
    private array $types = [];

    /** @var array<string, class-string<ApprovalHandler>> */
    private array $handlers = [];

    public function register(ApprovalDocumentType $type, string $handler): void
    {
        $this->types[$type->key] = $type;
        $this->handlers[$type->key] = $handler;
    }

    public function type(string $key): ApprovalDocumentType
    {
        return $this->types[$key] ?? throw new InvalidArgumentException("Unknown approval document type [{$key}].");
    }

    public function types(): array
    {
        return $this->types;
    }

    public function handler(string $key): ApprovalHandler
    {
        return app($this->handlers[$key] ?? throw new InvalidArgumentException("No approval handler for [{$key}]."));
    }
}
