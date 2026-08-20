<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToastTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_flash_is_rendered_as_toast(): void
    {
        $this->withSession(['success' => 'Сайт створено.'])
            ->get(route('sites.index'))
            ->assertOk()
            ->assertSee('id="toast-viewport"', false)
            ->assertSee('Сайт створено.');
    }

    public function test_error_flash_is_rendered_as_toast(): void
    {
        $this->withSession(['error' => 'Зараз уже виконується перевірка. Зачекайте завершення.'])
            ->get(route('sites.index'))
            ->assertOk()
            ->assertSee('Зараз уже виконується перевірка. Зачекайте завершення.');
    }

    public function test_validation_errors_are_rendered_as_toasts(): void
    {
        $this->followingRedirects()
            ->from(route('settings.index'))
            ->post(route('settings.restore'))
            ->assertOk()
            ->assertSee('Оберіть файл бази даних.');
    }
}
