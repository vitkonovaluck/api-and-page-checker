<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\BuildChromeExtensionZipAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ChromeExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('zip')) {
            $this->markTestSkipped('The PHP zip extension is required.');
        }
    }

    public function test_guests_can_download_the_chrome_extension_zip(): void
    {
        $this->get(route('extension.chrome'))
            ->assertOk()
            ->assertDownload((string) config('checking.extension_zip_filename'));
    }

    public function test_authenticated_users_can_download_the_chrome_extension_zip(): void
    {
        $this->actingAsUser();

        $this->get(route('extension.chrome'))
            ->assertOk()
            ->assertDownload((string) config('checking.extension_zip_filename'));
    }

    public function test_packaged_extension_includes_manifest_and_this_server_url(): void
    {
        $apiBaseUrl = 'https://checker.example.com';
        $package = app(BuildChromeExtensionZipAction::class)->execute($apiBaseUrl);

        $zip = new ZipArchive;
        $opened = $zip->open($package['path']);
        $this->assertTrue($opened === true);

        try {
            $this->assertNotFalse($zip->locateName('manifest.json'));
            $this->assertNotFalse($zip->locateName('popup.js'));
            $this->assertNotFalse($zip->locateName('defaults.js'));

            $defaults = $zip->getFromName('defaults.js');
            $this->assertIsString($defaults);
            $this->assertStringContainsString($apiBaseUrl, $defaults);
        } finally {
            $zip->close();
            unlink($package['path']);
        }
    }

    public function test_settings_page_links_to_the_extension_download(): void
    {
        $this->actingAsUser();

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee(__('landing.extension_download'))
            ->assertSee(route('extension.chrome'), false);
    }
}
