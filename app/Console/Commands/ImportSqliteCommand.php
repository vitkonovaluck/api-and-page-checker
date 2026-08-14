<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportSqliteCommand extends Command
{
    protected $signature = 'db:import-sqlite
                            {--path= : Path to the SQLite file}
                            {--if-empty : Skip when MySQL already has application data}';

    protected $description = 'Copy application data from a SQLite file into the current MySQL database';

    /**
     * @var list<string>
     */
    private const SKIP_TABLES = [
        'migrations',
        'sqlite_sequence',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
    ];

    public function handle(): int
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('Current connection is not MySQL.');

            return self::FAILURE;
        }

        $path = $this->resolveSqlitePath();
        if ($path === null) {
            $this->warn('No SQLite file found; skipping import.');

            return self::SUCCESS;
        }

        if ($this->option('if-empty') && $this->mysqlHasApplicationData()) {
            $this->info('MySQL already has data; skipping SQLite import.');

            return self::SUCCESS;
        }

        config([
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => $path,
        ]);
        DB::purge('sqlite');
        $sqlite = DB::connection('sqlite');

        try {
            $sqlite->getPdo();
        } catch (Throwable $e) {
            $this->error('Could not open SQLite file: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Importing from {$path}");

        $sqliteTables = collect($sqlite->getPdo()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_COLUMN))
            ->reject(fn (string $table) => in_array($table, self::SKIP_TABLES, true))
            ->values();

        $mysqlTables = collect(Schema::getTableListing())
            ->map(function (string $table): string {
                return str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            })
            ->reject(fn (string $table) => in_array($table, self::SKIP_TABLES, true))
            ->values();

        $this->line('SQLite tables: '.$sqliteTables->implode(', '));
        $this->line('MySQL tables: '.$mysqlTables->implode(', '));

        $tables = $mysqlTables->intersect($sqliteTables)->values();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                $this->importTable($table);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('SQLite import finished.');

        return self::SUCCESS;
    }

    private function resolveSqlitePath(): ?string
    {
        $candidates = array_values(array_filter([
            $this->option('path'),
            '/data/database.sqlite',
            database_path('database.sqlite'),
        ], fn ($path) => is_string($path) && $path !== ''));

        $existing = [];
        foreach ($candidates as $path) {
            if (is_file($path) && filesize($path) > 0) {
                $existing[] = $path;
            }
        }

        if ($existing === []) {
            return null;
        }

        $source = $existing[0];
        $bestSize = filesize($source) ?: 0;

        foreach ($existing as $path) {
            $size = filesize($path) ?: 0;
            if ($size > $bestSize) {
                $source = $path;
                $bestSize = $size;
            }
        }

        return $this->copyToLocalTemp($source);
    }

    private function copyToLocalTemp(string $path): string
    {
        $tmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'api-checker-import.sqlite';

        if (! copy($path, $tmp)) {
            throw new \RuntimeException("Could not copy SQLite file from {$path}");
        }

        foreach (['-wal', '-shm'] as $suffix) {
            if (is_file($path.$suffix)) {
                copy($path.$suffix, $tmp.$suffix);
            }
        }

        return $tmp;
    }

    private function mysqlHasApplicationData(): bool
    {
        foreach (['sites', 'addresses', 'snapshots'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function importTable(string $table): void
    {
        $sqliteColumns = collect(DB::connection('sqlite')->getPdo()->query('PRAGMA table_info("'.$table.'")')->fetchAll(\PDO::FETCH_ASSOC))
            ->pluck('name')
            ->all();
        $mysqlColumns = Schema::getColumnListing($table);
        $columns = array_values(array_intersect($mysqlColumns, $sqliteColumns));

        if ($columns === []) {
            return;
        }

        DB::table($table)->truncate();

        $imported = 0;
        $insertChunk = function ($rows) use ($table, $columns, &$imported): void {
            $payload = [];

            foreach ($rows as $row) {
                $record = [];
                foreach ($columns as $column) {
                    $record[$column] = $row->{$column} ?? null;
                }
                $payload[] = $record;
            }

            if ($payload !== []) {
                DB::table($table)->insert($payload);
                $imported += count($payload);
            }
        };

        $query = DB::connection('sqlite')->table($table);

        if (in_array('id', $columns, true)) {
            $query->orderBy('id')->chunkById(100, $insertChunk);
        } else {
            $insertChunk($query->get());
        }

        if (in_array('id', $columns, true)) {
            $maxId = (int) DB::table($table)->max('id');
            if ($maxId > 0) {
                DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT='.($maxId + 1));
            }
        }

        $this->line("  {$table}: {$imported} row(s)");
    }
}
