<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\ApprovalDocumentType;

/**
 * The document types that go through approval, each with the handler of its module (Step 5.1). Modules register
 * in their service providers.
 */
interface ApprovalRegistry
{
    /**
     * @param  class-string<ApprovalHandler>  $handler
     */
    public function register(ApprovalDocumentType $type, string $handler): void;

    public function type(string $key): ApprovalDocumentType;

    /**
     * @return array<string, ApprovalDocumentType>
     */
    public function types(): array;

    public function handler(string $key): ApprovalHandler;
}
