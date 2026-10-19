<?php

namespace Modules\HR\Services;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads a biometric attendance CSV (Step 6.2). Pure: what was read and the problems found, each with its line number.
 *
 * Two shapes, told apart by the header row (comma, semicolon or tab separated):
 * - punch list: an employee column and either one date-time column or a date column and a time column, one punch per line;
 * - daily: an employee column, a date column and clock-in and clock-out columns, one day per line.
 * Dates are Y-m-d, d/m/Y or d-m-Y; times H:i, H:i:s or h:i AM/PM.
 */
class AttendanceCsv
{
    private const array ALIASES = [
        'employee' => ['employee', 'employee no', 'employee number', 'employee id', 'emp no', 'emp id', 'staff id', 'staff no', 'badge', 'badge no', 'card no', 'user id', 'id'],
        'datetime' => ['datetime', 'date time', 'date/time', 'timestamp', 'time stamp', 'punch', 'punch time', 'punch datetime', 'check time'],
        'date' => ['date', 'work date', 'day', 'punch date'],
        'time' => ['time', 'punch time of day'],
        'in' => ['in', 'clock in', 'check in', 'time in', 'first in', 'arrival', 'in time'],
        'out' => ['out', 'clock out', 'check out', 'time out', 'last out', 'departure', 'out time'],
    ];

    /**
     * @return array{shape: string|null, punches: list<array{line: int, employee: string, at: string}>, days: list<array{line: int, employee: string, date: string, in: string|null, out: string|null}>, errors: list<string>, rows: int}
     */
    public function parse(string $csv): array
    {
        $rows = $this->rows($csv);
        $empty = ['shape' => null, 'punches' => [], 'days' => [], 'errors' => [], 'rows' => 0];

        if ($rows === []) {
            return [...$empty, 'errors' => [__('The file is empty.')]];
        }

        $header = array_map(fn (string $name): string => mb_strtolower(trim($name, " \t\n\r\0\x0B\xEF\xBB\xBF\"")), array_shift($rows));
        $columns = [];

        foreach (self::ALIASES as $field => $names) {
            foreach ($header as $index => $name) {
                if (in_array($name, $names, true) && ! in_array($index, $columns, true) && ! isset($columns[$field])) {
                    $columns[$field] = $index;
                }
            }
        }

        $daily = isset($columns['in'], $columns['out'], $columns['date']);
        $punchList = isset($columns['datetime']) || (isset($columns['date']) && isset($columns['time']));

        if (! isset($columns['employee']) || (! $daily && ! $punchList)) {
            return [...$empty, 'errors' => [__('The header row needs an employee column and either a date-time column, a date and a time column, or date, in and out columns.')]];
        }

        $result = [...$empty, 'shape' => $daily ? 'daily' : 'punches', 'rows' => count($rows)];

        foreach ($rows as $offset => $row) {
            $line = $offset + 2;
            $employee = trim($row[$columns['employee']] ?? '');

            if ($employee === '') {
                $result['errors'][] = __('Line :n: there is no employee.', ['n' => $line]);

                continue;
            }

            if ($daily) {
                $date = $this->date($row[$columns['date']] ?? '');
                $in = $this->time($row[$columns['in']] ?? '');
                $out = $this->time($row[$columns['out']] ?? '');

                if ($date === null) {
                    $result['errors'][] = __('Line :n: the date ":date" is not valid (use 2026-10-31 or 31/10/2026).', ['n' => $line, 'date' => trim($row[$columns['date']] ?? '')]);
                } elseif ($in === false || $out === false) {
                    $result['errors'][] = __('Line :n: a time is not valid (use 08:57 or 8:57 AM).', ['n' => $line]);
                } elseif ($in === null && $out === null) {
                    $result['errors'][] = __('Line :n: there is no clock-in or clock-out.', ['n' => $line]);
                } else {
                    $result['days'][] = ['line' => $line, 'employee' => $employee, 'date' => $date, 'in' => $in, 'out' => $out];
                }

                continue;
            }

            $at = isset($columns['datetime']) ? $this->dateTime($row[$columns['datetime']] ?? '') : $this->joined($row[$columns['date']] ?? '', $row[$columns['time']] ?? '');

            if ($at === null) {
                $result['errors'][] = __('Line :n: the punch time is not valid (use 2026-10-31 08:57).', ['n' => $line]);
            } else {
                $result['punches'][] = ['line' => $line, 'employee' => $employee, 'at' => $at];
            }
        }

        if ($result['punches'] === [] && $result['days'] === [] && $result['errors'] === []) {
            $result['errors'][] = __('The file has no attendance lines.');
        }

        return $result;
    }

    /**
     * @return list<list<string>>
     */
    private function rows(string $csv): array
    {
        $csv = trim(str_replace("\r\n", "\n", str_replace("\r", "\n", $csv)));

        if ($csv === '') {
            return [];
        }

        $first = strtok($csv, "\n") ?: '';
        $separator = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $rows = [];

        foreach (explode("\n", $csv) as $line) {
            if (trim($line) !== '') {
                $rows[] = array_map(fn (?string $cell): string => (string) $cell, str_getcsv($line, $separator, '"', ''));
            }
        }

        return $rows;
    }

    private function date(string $value): ?string
    {
        $value = trim($value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date instanceof CarbonImmutable && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /**
     * "H:i" for a time, null for an empty cell, false for text that is not a time.
     */
    private function time(string $value): string|false|null
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return null;
        }

        foreach (['H:i', 'H:i:s', 'G:i', 'G:i:s', 'g:i A', 'h:i A', 'h:i:s A'] as $format) {
            try {
                $time = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($time instanceof CarbonImmutable && CarbonImmutable::hasFormat($value, $format)) {
                return $time->format('H:i');
            }
        }

        return false;
    }

    private function dateTime(string $value): ?string
    {
        $value = trim($value);

        foreach (['Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'd/m/Y H:i', 'd/m/Y H:i:s', 'd-m-Y H:i', 'd-m-Y H:i:s'] as $format) {
            try {
                $at = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($at instanceof CarbonImmutable && CarbonImmutable::hasFormat($value, $format)) {
                return $at->format('Y-m-d H:i');
            }
        }

        return null;
    }

    private function joined(string $date, string $time): ?string
    {
        $date = $this->date($date);
        $time = $this->time($time);

        return $date !== null && is_string($time) ? $date.' '.$time : null;
    }
}
