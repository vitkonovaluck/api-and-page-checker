<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SettingsBackupTest extends TestCase
{
    public function test_settings_page_shows_sqlite_backup_when_using_sqlite(): void
    {
        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('SQLite')
            ->assertSee('.sqlite');
    }

    public function test_settings_page_shows_mysql_backup_copy(): void
    {
        $this->mock(DatabaseBackupService::class, function ($mock): void {
            $mock->shouldReceive('viewData')->once()->andReturn([
                'driver' => 'mysql',
                'is_mysql' => true,
                'is_sqlite' => false,
                'can_backup' => true,
                'database_name' => 'api_checker',
                'database_host' => '127.0.0.1',
                'database_port' => '3306',
                'sqlite_path' => null,
                'sqlite_exists' => false,
                'accepted_extensions' => '.sql',
                'accepted_accept' => '.sql',
            ]);
        });

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('MySQL')
            ->assertSee('api_checker')
            ->assertSee('.sql');
    }

    public function test_sqlite_backup_fails_for_in_memory_database(): void
    {
        $this->from(route('settings.index'))
            ->post(route('settings.backup'))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('backup');
    }

    public function test_can_download_sqlite_file_backup(): void
    {
        $path = $this->useFileSqlite();
        $this->artisan('migrate');

        try {
            $this->post(route('settings.backup'))
                ->assertOk()
                ->assertDownload();
        } finally {
            File::delete($path);
        }
    }

    public function test_restore_rejects_sql_file_on_sqlite(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'dump.sql',
            "SET NAMES utf8mb4;\nCREATE TABLE test (id int);\n"
        );

        $this->from(route('settings.index'))
            ->post(route('settings.restore'), ['database' => $file])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('database');
    }

    public function test_can_restore_sqlite_backup(): void
    {
        $live = $this->useFileSqlite();
        $this->artisan('migrate');

        $uploadPath = storage_path('framework/testing/settings-upload.sqlite');
        File::copy($live, $uploadPath);

        $upload = new UploadedFile($uploadPath, 'backup.sqlite', 'application/octet-stream', null, true);

        try {
            $this->from(route('settings.index'))
                ->post(route('settings.restore'), ['database' => $upload])
                ->assertRedirect(route('settings.index'))
                ->assertSessionHas('success');
        } finally {
            File::delete($live);
            File::delete($uploadPath);
        }
    }

    public function test_sql_dump_is_detected_and_sqlite_header_is_rejected(): void
    {
        $service = app(DatabaseBackupService::class);
        $dir = storage_path('framework/testing');
        File::ensureDirectoryExists($dir);

        $sql = $dir.'/sample.sql';
        File::put($sql, "-- MySQL dump\nSET FOREIGN_KEY_CHECKS=0;\nCREATE TABLE `users` (id int);\n");
        $this->assertTrue($service->looksLikeSqlDump($sql));

        $sqlite = $dir.'/sample.sqlite';
        File::put($sqlite, 'SQLite format 3'.str_repeat("\0", 16));
        $this->assertTrue($service->looksLikeSqlite($sqlite));
        $this->assertFalse($service->looksLikeSqlDump($sqlite));

        File::delete($sql);
        File::delete($sqlite);
    }

    private function useFileSqlite(): string
    {
        $path = storage_path('framework/testing/settings-live.sqlite');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '');

        config(['database.connections.sqlite.database' => $path]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        return $path;
    }
}
