<?php

namespace Modules\FrontOffice\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\FrontOffice\Jobs\RunDueNightAudits;
use Modules\FrontOffice\Models\NightAudit;

#[Signature('frontoffice:night-audit {--tenant= : Only this tenant (id)} {--property= : Only this property (id)} {--now : Run it now instead of waiting for the audit time}')]
#[Description('Run the night audits that are due (also scheduled every five minutes)')]
class NightAuditCommand extends Command
{
    public function handle(): int
    {
        $tenant = $this->option('tenant');
        $property = $this->option('property');
        /** @var list<NightAudit> $audits */
        $audits = RunDueNightAudits::dispatchSync(is_numeric($tenant) ? (int) $tenant : null, is_numeric($property) ? (int) $property : null, (bool) $this->option('now'));

        foreach ($audits as $audit) {
            $this->components->twoColumnDetail("Property {$audit->property_id} · {$audit->business_date->toDateString()}", $audit->status->label());

            foreach ($audit->issues ?? [] as $issue) {
                $this->components->warn($issue);
            }
        }

        $this->components->info('Night audits run: '.count($audits).'.');

        return self::SUCCESS;
    }
}
