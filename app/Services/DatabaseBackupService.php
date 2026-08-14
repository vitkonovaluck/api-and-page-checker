<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PDO;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    /**
     * @return array{driver: string, is_mysql: bool, is_sqlite: bool, can_backup: bool, database_name: ?string, database_host: ?string, database_port: ?string, sqlite_path: ?string, sqlite_exists: bool, accepted_extensions: string, accepted_accept: string}
     */
    public function viewData(): array
    {
        $sqlitePath = $this->sqlitePath();

        return [
            'driver' => $this->driver(),
            'is_mysql' => $this->isMysql(),
            'is_sqlite' => $this->isSqlite(),
            'can_backup' => $this->canBackup(),
            'database_name' => $this->databaseName(),
            'database_host' => $this->configValue('host'),
            'database_port' => $this->configValue('port'),
            'sqlite_path' => $sqlitePath,
            'sqlite_exists' => $sqlitePath !== null && File::exists($sqlitePath),
            'accepted_extensions' => $this->isMysql() ? '.sql' : '.sqlite / .db',
            'accepted_accept' => $this->isMysql() ? '.sql' : '.sqlite,.db',
        ];
    }

    public function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    public function isMysql(): bool
    {
        return in_array($this->driver(), ['mysql', 'mariadb'], true);
    }

    public function isSqlite(): bool
    {
        return $this->driver() === 'sqlite';
    }

    public function canBackup(): bool
    {
        if ($this->isSqlite()) {
            $path = $this->sqlitePath();

            return $path !== null && File::exists($path);
        }

        if ($this->isMysql()) {
            return filled($this->databaseName());
        }

        return false;
    }

    /**
     * @return array{path: string, filename: string, delete_after_send: bool}
     */
    public function create(): array
    {
        set_time_limit(0);

        if ($this->isSqlite()) {
            return $this->createSqliteBackup();
        }

        if ($this->isMysql()) {
            return $this->createMysqlBackup();
        }

        throw new RuntimeException('Бекап для драйвера '.$this->driver().' не підтримується.');
    }

    public function restore(string $uploadedPath, string $originalExtension): void
    {
        set_time_limit(0);

        $extension = strtolower($originalExtension);

        if ($this->isSqlite()) {
            $this->restoreSqlite($uploadedPath, $extension);

            return;
        }

        if ($this->isMysql()) {
            $this->restoreMysql($uploadedPath, $extension);

            return;
        }

        throw new RuntimeException('Відновлення для драйвера '.$this->driver().' не підтримується.');
    }

    public function looksLikeSqlite(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        return is_string($header) && str_starts_with($header, 'SQLite format 3');
    }

    public function looksLikeSqlDump(string $path): bool
    {
        if ($this->looksLikeSqlite($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $sample = fread($handle, 4096);
        fclose($handle);

        if (! is_string($sample) || trim($sample) === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(CREATE TABLE|INSERT INTO|DROP TABLE|REPLACE INTO|SET FOREIGN_KEY_CHECKS|SET NAMES)\b/i',
            $sample
        );
    }

    public function backupDir(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    private function createSqliteBackup(): array
    {
        $path = $this->sqlitePath();

        if ($path === null || ! File::exists($path)) {
            throw new RuntimeException('Файл бази даних не знайдено.');
        }

        $this->checkpointSqlite();

        return [
            'path' => $path,
            'filename' => 'api-checker-'.now()->format('Y-m-d-His').'.sqlite',
            'delete_after_send' => false,
        ];
    }

    private function createMysqlBackup(): array
    {
        $filename = 'api-checker-'.now()->format('Y-m-d-His').'.sql';
        $path = $this->backupDir().DIRECTORY_SEPARATOR.$filename;

        $this->dumpMysqlTo($path);

        return [
            'path' => $path,
            'filename' => $filename,
            'delete_after_send' => true,
        ];
    }

    private function dumpMysqlTo(string $path): void
    {
        $dumped = false;
        $binary = $this->findBinary('mysqldump');

        if ($binary !== null) {
            try {
                $this->runMysqlClient($binary, [
                    '--single-transaction',
                    '--quick',
                    '--add-drop-table',
                    '--default-character-set=utf8mb4',
                    '--result-file='.str_replace('\\', '/', $path),
                    (string) $this->databaseName(),
                ]);
                $dumped = File::exists($path) && File::size($path) > 0;
            } catch (Throwable) {
                $dumped = false;
            }
        }

        if (! $dumped) {
            $this->dumpMysqlWithPhp($path);
        }

        if (! File::exists($path) || File::size($path) === 0) {
            throw new RuntimeException('Не вдалося створити SQL-дамп бази даних.');
        }
    }

    private function dumpMysqlWithPhp(string $path): void
    {
        $connection = DB::connection();
        $database = (string) $this->databaseName();
        $pdo = $connection->getPdo();

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Не вдалося записати файл бекапу.');
        }

        try {
            fwrite($handle, "-- API Checker MySQL dump\n");
            fwrite($handle, '-- Database: '.$database."\n");
            fwrite($handle, '-- Date: '.now()->toDateTimeString()."\n\n");
            fwrite($handle, "SET NAMES utf8mb4;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = $connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tableKey = 'Tables_in_'.$database;

            foreach ($tables as $row) {
                $table = (string) ($row->{$tableKey} ?? '');
                if ($table === '') {
                    continue;
                }

                $create = $connection->selectOne('SHOW CREATE TABLE `'.$table.'`');
                $createSql = $create->{'Create Table'} ?? null;
                if (! is_string($createSql) || $createSql === '') {
                    continue;
                }

                fwrite($handle, 'DROP TABLE IF EXISTS `'.$table."`;\n");
                fwrite($handle, $createSql.";\n\n");

                $statement = $pdo->query('SELECT * FROM `'.$table.'`');
                if ($statement === false) {
                    continue;
                }

                $batch = [];
                while ($record = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $batch[] = $this->sqlValueTuple($pdo, $record);
                    if (count($batch) >= 80) {
                        fwrite($handle, $this->insertStatement($table, $batch));
                        $batch = [];
                    }
                }
                $statement->closeCursor();

                if ($batch !== []) {
                    fwrite($handle, $this->insertStatement($table, $batch));
                }

                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string>  $tuples
     */
    private function insertStatement(string $table, array $tuples): string
    {
        return 'INSERT INTO `'.$table."` VALUES\n".implode(",\n", $tuples).";\n";
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function sqlValueTuple(PDO $pdo, array $record): string
    {
        $values = [];

        foreach ($record as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = $pdo->quote((string) $value);
            }
        }

        return '('.implode(',', $values).')';
    }

    private function restoreSqlite(string $uploadedPath, string $extension): void
    {
        if (! in_array($extension, ['sqlite', 'db'], true)) {
            throw new RuntimeException('Дозволені лише файли .sqlite або .db.');
        }

        $target = $this->sqlitePath();
        if ($target === null) {
            throw new RuntimeException('SQLite-файл бази не налаштовано.');
        }

        if (! $this->looksLikeSqlite($uploadedPath)) {
            throw new RuntimeException('Файл не схожий на SQLite базу даних.');
        }

        $this->checkpointSqlite();
        DB::disconnect();

        if (File::exists($target)) {
            File::copy(
                $target,
                $this->backupDir().DIRECTORY_SEPARATOR.'pre-restore-'.now()->format('Y-m-d-His').'.sqlite'
            );
        }

        File::copy($uploadedPath, $target);
        $this->deleteSqliteSidecars($target);
        DB::reconnect();
    }

    private function restoreMysql(string $uploadedPath, string $extension): void
    {
        if ($extension !== 'sql') {
            throw new RuntimeException('Для MySQL дозволені лише файли .sql.');
        }

        if (! $this->looksLikeSqlDump($uploadedPath)) {
            throw new RuntimeException('Файл не схожий на SQL-дамп MySQL.');
        }

        $safetyCopy = $this->backupDir().DIRECTORY_SEPARATOR.'pre-restore-'.now()->format('Y-m-d-His').'.sql';
        $this->dumpMysqlTo($safetyCopy);

        try {
            $this->importMysql($uploadedPath);
        } catch (Throwable $e) {
            try {
                $this->importMysql($safetyCopy);
            } catch (Throwable) {
                // Keep the original exception; safety copy remains on disk.
            }

            throw new RuntimeException('Не вдалося відновити базу: '.$e->getMessage());
        }

        DB::purge();
        DB::reconnect();
    }

    private function importMysql(string $sqlPath): void
    {
        $binary = $this->findBinary('mysql');

        if ($binary !== null) {
            $handle = fopen($sqlPath, 'rb');
            if ($handle !== false) {
                try {
                    $this->runMysqlClient($binary, [
                        '--default-character-set=utf8mb4',
                        (string) $this->databaseName(),
                    ], $handle);

                    return;
                } catch (Throwable) {
                    // Fall back to PHP import if the mysql client fails.
                } finally {
                    if (is_resource($handle)) {
                        fclose($handle);
                    }
                }
            }
        }

        $this->importMysqlWithPhp($sqlPath);
    }

    private function importMysqlWithPhp(string $path): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Не вдалося прочитати SQL-файл.');
        }

        try {
            $buffer = '';
            $inString = false;
            $stringChar = '';
            $escaped = false;

            while (($chunk = fread($handle, 8192)) !== false && $chunk !== '') {
                $length = strlen($chunk);

                for ($i = 0; $i < $length; $i++) {
                    $char = $chunk[$i];
                    $buffer .= $char;

                    if ($inString) {
                        if ($escaped) {
                            $escaped = false;

                            continue;
                        }

                        if ($char === '\\' && $stringChar === "'") {
                            $escaped = true;

                            continue;
                        }

                        if ($char === $stringChar) {
                            $inString = false;
                        }

                        continue;
                    }

                    if ($char === "'" || $char === '"' || $char === '`') {
                        $inString = true;
                        $stringChar = $char;

                        continue;
                    }

                    if ($char === ';') {
                        $this->execSqlStatement($pdo, $buffer);
                        $buffer = '';
                    }
                }
            }

            $this->execSqlStatement($pdo, $buffer);
        } finally {
            fclose($handle);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function execSqlStatement(PDO $pdo, string $sql): void
    {
        $statement = trim($sql);
        if ($statement === '' || $statement === ';') {
            return;
        }

        $pdo->exec($statement);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runMysqlClient(string $binary, array $arguments, mixed $input = null): void
    {
        $cnf = $this->writeMysqlDefaultsFile();

        try {
            $pending = Process::timeout(600)->command(array_merge(
                [$binary, '--defaults-extra-file='.str_replace('\\', '/', $cnf)],
                $arguments,
            ));

            if ($input !== null) {
                $pending = $pending->input($input);
            }

            $result = $pending->run();

            if ($result->failed()) {
                $error = trim($result->errorOutput().' '.$result->output());

                throw new RuntimeException($error !== '' ? $error : 'Команда MySQL завершилась з помилкою.');
            }
        } finally {
            File::delete($cnf);
        }
    }

    private function writeMysqlDefaultsFile(): string
    {
        $connection = (string) config('database.default');
        $config = config('database.connections.'.$connection, []);
        $path = $this->backupDir().DIRECTORY_SEPARATOR.'mysql-client-'.uniqid('', true).'.cnf';

        $user = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $socket = (string) ($config['unix_socket'] ?? '');

        $lines = [
            '[client]',
            'user='.$this->cnfQuote($user),
            'password='.$this->cnfQuote($password),
        ];

        if ($socket !== '') {
            $lines[] = 'socket='.$this->cnfQuote($socket);
        } else {
            $lines[] = 'host='.$this->cnfQuote($host);
            $lines[] = 'port='.$port;
        }

        File::put($path, implode("\n", $lines)."\n");

        return $path;
    }

    private function cnfQuote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function findBinary(string $name): ?string
    {
        $exe = PHP_OS_FAMILY === 'Windows' ? $name.'.exe' : $name;
        $configKey = $name === 'mysqldump' ? 'mysqldump_path' : 'mysql_path';
        $configured = config('database.dump.'.$configKey);

        $candidates = array_filter([
            is_string($configured) && $configured !== '' ? $configured : null,
            dirname(base_path(), 3).DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.$exe,
            'C:\\xampp\\mysql\\bin\\'.$exe,
            'D:\\xampp\\mysql\\bin\\'.$exe,
            '/usr/bin/'.$name,
            '/usr/local/bin/'.$name,
            '/usr/local/mysql/bin/'.$name,
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path)) {
                return $path;
            }
        }

        $locator = PHP_OS_FAMILY === 'Windows'
            ? ['where', $exe]
            : ['command', '-v', $name];

        try {
            $result = Process::timeout(5)->run($locator);
            if ($result->successful()) {
                $found = trim((string) strtok($result->output(), "\n"));
                if ($found !== '' && is_file($found)) {
                    return $found;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function checkpointSqlite(): void
    {
        try {
            DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (Throwable) {
            // WAL may be off, or the connection may already be closed.
        }
    }

    private function deleteSqliteSidecars(string $path): void
    {
        foreach (['-wal', '-shm', '-journal'] as $suffix) {
            File::delete($path.$suffix);
        }
    }

    private function sqlitePath(): ?string
    {
        if (! $this->isSqlite()) {
            return null;
        }

        $path = (string) config('database.connections.sqlite.database');

        if ($path === '' || $path === ':memory:') {
            return null;
        }

        return $path;
    }

    private function databaseName(): ?string
    {
        $name = DB::connection()->getDatabaseName();

        return filled($name) ? (string) $name : null;
    }

    private function configValue(string $key): ?string
    {
        $connection = (string) config('database.default');
        $value = config('database.connections.'.$connection.'.'.$key);

        return filled($value) ? (string) $value : null;
    }
}
