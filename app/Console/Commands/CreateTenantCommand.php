<?php

namespace App\Console\Commands;

use App\Actions\Tenancy\CreateTenant;
use App\Enums\TenantStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('tenant:create {slug : Subdomain, e.g. rodela} {name : Company name} {--email= : Contact email} {--status=active : trial, active, suspended or cancelled}')]
#[Description('Create a tenant (development helper)')]
class CreateTenantCommand extends Command
{
    public function handle(CreateTenant $createTenant): int
    {
        $status = TenantStatus::tryFrom((string) $this->option('status'));

        if ($status === null) {
            $this->components->error('Unknown status. Use one of: '.implode(', ', TenantStatus::values()).'.');

            return self::FAILURE;
        }

        try {
            $tenant = $createTenant->handle(
                strtolower((string) $this->argument('slug')),
                (string) $this->argument('name'),
                $this->option('email') ? (string) $this->option('email') : null,
                $status,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $this->components->info("Tenant [{$tenant->name}] created: {$tenant->url()}");

        return self::SUCCESS;
    }
}
