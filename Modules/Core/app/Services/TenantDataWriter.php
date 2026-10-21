<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Writes all of one company's data into a ZIP (Step 7.4): a CSV per table that has a `tenant_id` column (every table a company owns,
 * whatever module made it), and its attachments under `files/`. Secrets are left out: passwords, tokens, 2FA secrets, recovery codes
 * and PINs. Values the system keeps encrypted (ID and bank account numbers) stay encrypted.
 */
class TenantDataWriter
{
    /** Columns never exported, matched on whole words of the column name. */
    private const string SECRET_COLUMNS = '/(^|_)(password|secret|token|recovery|pin)(_|$)/';

    /**
     * @return array{tables: int, rows: int, files: int}
     */
    public function write(int $tenantId, string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create '.$zipPath);
        }

        $temporary = [];
        $counts = ['tables' => 0, 'rows' => 0, 'files' => 0];

        try {
            foreach ($this->tables() as $table) {
                $columns = array_values(array_filter(Schema::getColumnListing($table), fn (string $column): bool => preg_match(self::SECRET_COLUMNS, $column) !== 1));
                $file = tempnam(sys_get_temp_dir(), 'export');
                $handle = fopen((string) $file, 'w');

                if ($file === false || $handle === false) {
                    throw new RuntimeException('Cannot write a temporary file.');
                }

                $temporary[] = $file;
                fputcsv($handle, $columns, escape: '');
                $rows = 0;

                foreach (DB::table($table)->where('tenant_id', $tenantId)->select($columns)->cursor() as $row) {
                    fputcsv($handle, array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : (string) json_encode($value), (array) $row), escape: '');
                    $rows++;
                }

                fclose($handle);
                $zip->addFile($file, 'data/'.$table.'.csv');
                $counts['tables']++;
                $counts['rows'] += $rows;
            }

            $disk = Storage::disk((string) config('media-library.disk_name'));

            foreach ($disk->allFiles('tenants/'.$tenantId.'/media') as $path) {
                $file = tempnam(sys_get_temp_dir(), 'export');
                $stream = $disk->readStream($path);

                if ($file === false || ! is_resource($stream)) {
                    continue;
                }

                $temporary[] = $file;
                file_put_contents($file, $stream);
                $zip->addFile($file, 'files/'.substr($path, strlen('tenants/'.$tenantId.'/media/')));
                $counts['files']++;
            }

            $zip->addFromString('README.txt', __("This is all of your company's data at :time.\n\ndata/ holds one CSV file per table (UTF-8, comma separated, first line = column names; ids link the tables).\nfiles/ holds your attachments, by attachment id (see data/media.csv).\nPasswords, PINs and security keys are not included. ID and bank account numbers stay encrypted.\n", ['time' => now()->toIso8601String()]));
            $zip->close();
        } finally {
            foreach ($temporary as $file) {
                @unlink($file);
            }
        }

        return $counts;
    }

    /**
     * The database's tables that hold a company's rows.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        $tables = Schema::getTableListing(Schema::getCurrentSchemaListing(), false);
        sort($tables);

        return array_values(array_filter($tables, fn (string $table): bool => Schema::hasColumn($table, 'tenant_id')));
    }
}
