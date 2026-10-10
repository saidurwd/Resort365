<?php

namespace Modules\Accounting\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Modules\Accounting\DTOs\Report;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads a report as Excel (.xlsx), CSV or PDF (Step 4.5). Figures are exported as numbers, not as the
 * formatted text of the screen.
 */
class StatementExport
{
    public function download(Report $report, string $format, string $name): Response|BinaryFileResponse
    {
        return match ($format) {
            'xlsx' => $this->xlsx($report, $name),
            'pdf' => $this->pdf($report, $name),
            default => $this->csv($report, $name),
        };
    }

    /**
     * @return list<list<float|string>>
     */
    public function table(Report $report): array
    {
        $table = [[$report->title], [$report->subtitle], [], $report->columns];

        foreach ($report->rows as $row) {
            $table[] = array_map(fn (string $cell, int $index): float|string => $index > 0 ? $this->number($cell) : str_repeat('  ', $row['indent'] ?? 0).$cell, $row['cells'], array_keys($row['cells']));
        }

        foreach ($report->notes as $note) {
            $table[] = [];
            $table[] = [$note];
        }

        return $table;
    }

    private function xlsx(Report $report, string $name): BinaryFileResponse
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'rpt');
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($this->table($report) as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return response()->download($path, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    private function csv(Report $report, string $name): Response
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($this->table($report) as $row) {
            fputcsv($handle ?: throw new RuntimeException('No temp stream.'), $row, ',', '"', '');
        }

        rewind($handle ?: throw new RuntimeException('No temp stream.'));

        return response((string) stream_get_contents($handle), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$name.'.csv"']);
    }

    private function pdf(Report $report, string $name): Response
    {
        return Pdf::loadView('accounting::reports.pdf', ['report' => $report])->setPaper('a4', count($report->columns) > 5 ? 'landscape' : 'portrait')->download($name.'.pdf');
    }

    private function number(string $cell): float|string
    {
        $clean = str_replace(',', '', $cell);

        return preg_match('/^-?\d+(\.\d+)?$/', $clean) === 1 ? (float) $clean : $cell;
    }
}
