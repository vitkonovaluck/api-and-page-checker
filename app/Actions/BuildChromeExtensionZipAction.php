<?php

declare(strict_types=1);

namespace App\Actions;

use JsonException;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BuildChromeExtensionZipAction
{
    /**
     * @var list<string>
     */
    private const SOURCE_FILES = [
        'manifest.json',
        'background.js',
        'popup.html',
        'popup.js',
    ];

    /**
     * @return array{path: string, filename: string}
     */
    public function execute(string $apiBaseUrl): array
    {
        $this->assertZipExtensionAvailable();

        $sourceDir = (string) config('checking.extension_directory', base_path('extension'));
        $filename = (string) config('checking.extension_zip_filename', 'api-checker-recorder.zip');
        $path = $this->temporaryZipPath();

        try {
            $this->writeArchive($path, $sourceDir, $apiBaseUrl);
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }

            throw $exception;
        }

        return [
            'path' => $path,
            'filename' => $filename,
        ];
    }

    private function writeArchive(string $path, string $sourceDir, string $apiBaseUrl): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the extension zip archive.');
        }

        try {
            foreach (self::SOURCE_FILES as $file) {
                $zip->addFromString($file, $this->readSourceFile($sourceDir, $file));
            }

            $zip->addFromString('defaults.js', $this->defaultsScript($apiBaseUrl));
        } finally {
            $zip->close();
        }
    }

    private function assertZipExtensionAvailable(): void
    {
        if (extension_loaded('zip') && class_exists(ZipArchive::class)) {
            return;
        }

        throw new RuntimeException('The PHP zip extension is required to package the browser extension.');
    }

    private function temporaryZipPath(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'apiext-'.bin2hex(random_bytes(8)).'.zip';
    }

    private function readSourceFile(string $sourceDir, string $file): string
    {
        $path = $sourceDir.DIRECTORY_SEPARATOR.$file;

        if (! is_file($path)) {
            throw new RuntimeException('Missing extension file: '.$file);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Could not read extension file: '.$file);
        }

        return $contents;
    }

    private function defaultsScript(string $apiBaseUrl): string
    {
        try {
            $encoded = json_encode(
                rtrim($apiBaseUrl, '/'),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not encode the checker URL for the extension.', 0, $exception);
        }

        return "'use strict';\n\nconst DEFAULT_API_BASE_URL = {$encoded};\n";
    }
}
