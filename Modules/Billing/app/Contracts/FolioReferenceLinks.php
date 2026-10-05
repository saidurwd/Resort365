<?php

namespace Modules\Billing\Contracts;

use Closure;

/**
 * Where a folio line posted by another module leads (Step 3.7: a restaurant bill's receipt), so the
 * folio can show and reprint it without Billing knowing the module. Modules register a resolver for
 * their reference type in their service provider; Billing asks for the link when it shows the line.
 */
final class FolioReferenceLinks
{
    /** @var array<string, Closure(int): (array{label: string, url: string}|null)> */
    private array $resolvers = [];

    /**
     * @param  Closure(int): (array{label: string, url: string}|null)  $resolver
     */
    public function register(string $referenceType, Closure $resolver): void
    {
        $this->resolvers[$referenceType] = $resolver;
    }

    /**
     * @return array{label: string, url: string}|null
     */
    public function link(?string $referenceType, ?int $referenceId): ?array
    {
        return $referenceType !== null && $referenceId !== null && isset($this->resolvers[$referenceType]) ? ($this->resolvers[$referenceType])($referenceId) : null;
    }
}
