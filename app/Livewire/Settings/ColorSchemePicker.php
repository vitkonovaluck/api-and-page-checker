<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\ColorAccent;
use App\Enums\ColorScheme;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

final class ColorSchemePicker extends Component
{
    public string $colorScheme;

    public bool $compact = false;

    public function mount(): void
    {
        $this->colorScheme = $this->currentUser()->color_scheme->value;
    }

    public function select(string $colorScheme): void
    {
        $scheme = ColorScheme::tryFrom($colorScheme);

        if ($scheme === null) {
            $this->addError('colorScheme', __('validation.enum', ['attribute' => 'colorScheme']));

            return;
        }

        $user = $this->currentUser();
        $user->color_scheme = $scheme;
        $user->save();

        $this->colorScheme = $scheme->value;
        $this->dispatch('color-scheme-changed', scheme: $scheme->value);
    }

    #[On('color-scheme-changed')]
    public function syncFromEvent(string $scheme): void
    {
        $this->colorScheme = $scheme;
    }

    public function render(): View
    {
        return view('livewire.settings.color-scheme-picker', [
            'current' => ColorScheme::tryFrom($this->colorScheme) ?? ColorScheme::default(),
            'accents' => ColorAccent::cases(),
        ]);
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
