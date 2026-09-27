<?php

namespace App\Support\Tenancy;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * File storage with paths prefixed `tenants/{tenant_id}/` (ARCHITECTURE §4.2).
 * Files are served only through authorized routes; for files attached to records use Core attachments
 * (App\Support\Attachments\HasAttachments).
 */
class TenantStorage
{
    public function __construct(private readonly TenantContext $context) {}

    public function path(string $path = ''): string
    {
        $prefix = 'tenants/'.$this->context->tenantOrFail(self::class)->id;

        return $path === '' ? $prefix : $prefix.'/'.ltrim($path, '/');
    }

    public function disk(?string $disk = null): Filesystem
    {
        return Storage::disk($disk);
    }

    public function put(string $path, string $contents, ?string $disk = null): bool
    {
        return $this->disk($disk)->put($this->path($path), $contents);
    }

    public function get(string $path, ?string $disk = null): ?string
    {
        return $this->disk($disk)->get($this->path($path));
    }

    public function exists(string $path, ?string $disk = null): bool
    {
        return $this->disk($disk)->exists($this->path($path));
    }

    public function delete(string $path, ?string $disk = null): bool
    {
        return $this->disk($disk)->delete($this->path($path));
    }
}
