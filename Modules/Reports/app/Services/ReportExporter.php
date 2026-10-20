<?php

namespace Modules\Reports\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reports\Enums\ExportFormat;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Makes the Excel or PDF file of a report table (Step 7.1) and returns its contents. Figures are exported as numbers in Excel,
 * not as the formatted text of the screen.
 */
class ReportExporter
{
    public function make(ReportTable $table, ExportFormat $format, string $subtitle): string
    {
        return match ($format) {
            ExportFormat::Xlsx => $this->xlsx($table, $subtitle),
            ExportFormat::Pdf => $this->pdf($table, $subtitle),
        };
    }

    /**
     * @return list<list<float|string>>
     */
    public function sheet(ReportTable $table, string $subtitle): array
    {
        $sheet = [[$table->title], [$subtitle], []];

        foreach ($table->summary as $figure) {
            $sheet[] = [$figure['label'], $this->number($figure['value'])];
        }

        $sheet[] = [];
        $sheet[] = $table->columns;

        foreach ($table->rows as $row) {
            $sheet[] = array_map($this->number(...), $row);
        }

        if ($table->totals !== null) {
            $sheet[] = array_map($this->number(...), $table->totals);
        }

        if ($table->note !== null) {
            $sheet[] = [];
            $sheet[] = [$table->note];
        }

        return $sheet;
    }

    private function xlsx(ReportTable $table, string $subtitle): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'rpt');
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($this->sheet($table, $subtitle) as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
        $contents = (string) file_get_contents($path);
        unlink($path);

        return $contents;
    }

    private function pdf(ReportTable $table, string $subtitle): string
    {
        $pdf = Pdf::loadView('reports::exports.pdf', ['table' => $table, 'subtitle' => $subtitle])->setPaper('a4', count($table->columns) > 5 ? 'landscape' : 'portrait');

        return $pdf->output() ?: throw new RuntimeException('The PDF could not be made.');
    }

    private function number(string $cell): float|string
    {
        $clean = str_replace(',', '', $cell);

        return preg_match('/^-?\d+(\.\d+)?$/', $clean) === 1 ? (float) $clean : $cell;
    }
}
