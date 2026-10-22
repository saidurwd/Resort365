<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use ZipArchive;

/**
 * The restore drill (Step 7.5, docs/OPERATIONS.md): restores the newest backup into a scratch database and checks it is usable —
 * every migration is there, every tenant came back, and encrypted fields still decrypt with this APP_KEY. It never touches the live
 * database. Run it monthly; on staging, run it against the backup copied from production.
 */
#[Signature('backup:restore-drill {--database=resort365_restore_drill : The scratch database to restore into} {--disk= : The backup disk (default: the first backup disk)} {--keep : Keep the scratch database afterwards}')]
#[Description('Restore the newest backup into a scratch database and check it')]
class BackupRestoreDrillCommand extends Command
{
    /** Tables whose row counts are compared with the live database. */
    private const array COUNTED = ['tenants', 'users', 'properties', 'rooms', 'guests', 'reservations', 'payments', 'journal_entries', 'employees'];

    public function handle(): int
    {
        $database = (string) $this->option('database');
        $live = (string) config('database.connections.'.config('database.default').'.database');

        if ($database === $live || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            $this->error(__('Choose a scratch database other than the live one (letters, digits and underscores).'));

            return self::FAILURE;
        }

        $backup = $this->newestBackup();

        if (! $backup instanceof Backup) {
            $this->error(__('No backup was found. Run `php artisan backup:run` first.'));

            return self::FAILURE;
        }

        $this->info(__('Restoring :path (:date) into :database.', ['path' => $backup->path(), 'date' => $backup->date()->toDateTimeString(), 'database' => $database]));
        $work = storage_path('app/restore-drill/'.now()->format('YmdHis'));
        File::ensureDirectoryExists($work);

        $created = false;

        try {
            $dump = $this->extractDump($backup, $work);
            $created = $this->prepareScratch($database);
            $this->import($dump, $database);

            $failures = $this->check($database, $live);
        } catch (RuntimeException|QueryException $exception) {
            $this->error($exception->getMessage());
            $failures = 1;
        } finally {
            File::deleteDirectory($work);

            if (! $this->option('keep')) {
                $this->cleanUp($database, $created);
            }
        }

        $failures === 0 ? $this->info(__('Restore drill passed.')) : $this->error(__('Restore drill failed.'));

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Creates the scratch database, or empties one a DBA made for the drill (a user without CREATE may still own one).
     *
     * @return bool whether this run created it
     */
    private function prepareScratch(string $database): bool
    {
        if ($this->scratchExists($database)) {
            if (DB::table('information_schema.tables')->where('table_schema', $database)->exists()) {
                throw new RuntimeException(__('The scratch database :database is not empty. Empty it or choose another with --database.', ['database' => $database]));
            }

            return false;
        }

        try {
            DB::statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (QueryException) {
            throw new RuntimeException(__('This database user may not create databases. Create an empty database :database, give this user all rights on it, and run the drill again.', ['database' => $database]));
        }

        return true;
    }

    private function scratchExists(string $database): bool
    {
        return DB::table('information_schema.schemata')->where('schema_name', $database)->exists();
    }

    /** Drops the scratch database this run created, or empties the one it was given. */
    private function cleanUp(string $database, bool $created): void
    {
        if (! $this->scratchExists($database)) {
            return;
        }

        try {
            if ($created) {
                DB::statement("DROP DATABASE `{$database}`");

                return;
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            foreach (DB::table('information_schema.tables')->where('table_schema', $database)->pluck('table_name') as $table) {
                DB::statement("DROP TABLE `{$database}`.`{$table}`");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (QueryException $exception) {
            $this->warn(__('The scratch database could not be cleaned up: :error', ['error' => $exception->getMessage()]));
        }
    }

    private function newestBackup(): ?Backup
    {
        $disks = (array) config('backup.backup.destination.disks');
        $disk = (string) ($this->option('disk') ?: ($disks[0] ?? 'backups'));

        return BackupDestination::create($disk, (string) config('backup.backup.name'))->newestBackup();
    }

    /** Copies the backup here, opens it (with the archive password when set) and returns the SQL dump's path. */
    private function extractDump(Backup $backup, string $work): string
    {
        $zipPath = $work.'/backup.zip';
        $stream = $backup->stream();
        file_put_contents($zipPath, $stream);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException(__('The backup archive cannot be opened.'));
        }

        $password = config('backup.backup.password');

        if (is_string($password) && $password !== '') {
            $zip->setPassword($password);
        }

        if (! $zip->extractTo($work)) {
            throw new RuntimeException(__('The backup archive cannot be extracted (wrong BACKUP_ARCHIVE_PASSWORD?).'));
        }

        $zip->close();
        $dumps = glob($work.'/db-dumps/*.sql*') ?: [];

        if ($dumps === []) {
            throw new RuntimeException(__('The backup has no database dump.'));
        }

        $dump = $dumps[0];

        if (str_ends_with($dump, '.gz')) {
            Process::run(['gunzip', $dump])->throw();
            $dump = substr($dump, 0, -3);
        }

        return $dump;
    }

    private function import(string $dump, string $database): void
    {
        $connection = (array) config('database.connections.'.config('database.default'));
        $result = Process::timeout(1800)->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])->input(fopen($dump, 'r'))->run([
            'mysql', '--host='.$connection['host'], '--port='.$connection['port'], '--user='.$connection['username'], '--default-character-set=utf8mb4', $database,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException(__('The import failed: :error', ['error' => mb_substr($result->errorOutput(), 0, 500)]));
        }
    }

    /**
     * @return int how many checks failed
     */
    private function check(string $database, string $live): int
    {
        $failures = 0;
        $restored = fn (string $table): int => (int) (DB::selectOne("select count(*) as n from `{$database}`.`{$table}`")->n ?? 0);
        $current = fn (string $table): int => (int) (DB::selectOne("select count(*) as n from `{$live}`.`{$table}`")->n ?? 0);

        $missing = DB::table($live.'.migrations')->whereNotIn('migration', DB::table($database.'.migrations')->pluck('migration'))->pluck('migration');
        $this->line(__('Migrations: :count restored, :missing newer than the backup.', ['count' => $restored('migrations'), 'missing' => $missing->count()]));

        $rows = [];

        foreach (self::COUNTED as $table) {
            $rows[] = [$table, $restored($table), $current($table)];
        }

        $this->table([__('Table'), __('In the backup'), __('Live now')], $rows);

        if ($restored('tenants') === 0) {
            $this->error(__('The backup has no tenants.'));
            $failures++;
        }

        $sample = DB::table($database.'.guests')->whereNotNull('id_number')->limit(20)->pluck('id_number');

        foreach ($sample as $value) {
            try {
                Crypt::decryptString((string) $value);
            } catch (DecryptException) {
                $this->error(__('Encrypted guest data does not decrypt with this APP_KEY.'));
                $failures++;

                break;
            }
        }

        $this->line(__('Encrypted fields checked: :count.', ['count' => $sample->count()]));

        return $failures;
    }
}
