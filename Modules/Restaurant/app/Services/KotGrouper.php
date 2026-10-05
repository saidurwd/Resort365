<?php

namespace Modules\Restaurant\Services;

/**
 * Which lines a Send puts on which kitchen ticket (ARCHITECTURE §5.10.6), without the database: the
 * pending lines that are not held (or, firing a course, the held lines of that course), one ticket per
 * station, stations in the order their lines were added.
 */
class KotGrouper
{
    /**
     * @param  list<array{id: int, station_id: int|null, status: string, held: bool, course: string}>  $lines
     * @return array<int|string, list<int>> station id (or "none") => line ids
     */
    public function group(array $lines, ?string $fireCourse = null): array
    {
        $tickets = [];

        foreach ($lines as $line) {
            if ($line['status'] !== 'pending') {
                continue;
            }

            $goes = $fireCourse === null ? ! $line['held'] : ($line['held'] && $line['course'] === $fireCourse);

            if ($goes) {
                $tickets[$line['station_id'] ?? 'none'][] = $line['id'];
            }
        }

        return $tickets;
    }
}
