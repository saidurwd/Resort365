<?php

namespace App\Support\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A tabular report as a CSV download (UTF-8 with a byte-order mark so spreadsheets read it right).
 */
final class CsvExport
{
    /**
     * @param  array{columns: list<string>, rows: list<list<string>>, totals?: list<string>|null}  $data
     */
    public static function response(array $data, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $data['columns'], escape: '\\');

            foreach ($data['rows'] as $row) {
                fputcsv($out, $row, escape: '\\');
            }

            if (($data['totals'] ?? null) !== null) {
                fputcsv($out, $data['totals'], escape: '\\');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
