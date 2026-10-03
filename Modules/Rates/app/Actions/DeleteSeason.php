<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Models\Season;

/**
 * Deletes a season with its periods and its rates (rate plans fall back to their base rates).
 */
class DeleteSeason extends Action
{
    public function handle(Season $season): void
    {
        $this->transaction(function () use ($season): void {
            $season->rates()->delete();
            $season->periods()->delete();
            $season->delete();
        });
    }
}
