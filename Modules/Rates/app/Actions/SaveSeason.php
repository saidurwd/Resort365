<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Arr;
use Modules\Rates\Models\Season;
use Modules\Rates\Models\SeasonPeriod;

/**
 * Creates or updates a season with its periods (validated by SaveSeasonRequest). Period changes
 * are recorded in the season's audit trail.
 */
class SaveSeason extends Action
{
    /**
     * @param  array<string, mixed>  $data  fields plus periods: list of {start_date, end_date}
     */
    public function handle(?Season $season, array $data): Season
    {
        return $this->transaction(function () use ($season, $data): Season {
            $season ??= new Season;
            $season->fill(Arr::except($data, 'periods'))->save();

            $old = $this->describe($season);
            $season->periods()->delete();

            foreach ((array) ($data['periods'] ?? []) as $period) {
                $season->periods()->create([
                    'property_id' => $season->property_id,
                    'start_date' => $period['start_date'],
                    'end_date' => $period['end_date'],
                ]);
            }

            $new = $this->describe($season);

            if ($old !== $new) {
                activity()->performedOn($season)->event('updated')
                    ->withProperties(['old' => ['periods' => $old], 'attributes' => ['periods' => $new]])->log('updated');
            }

            return $season;
        });
    }

    private function describe(Season $season): string
    {
        return $season->periods()->get()->map(fn (SeasonPeriod $period): string => $period->start_date->format('d M Y').' – '.$period->end_date->format('d M Y'))->implode('; ');
    }
}
