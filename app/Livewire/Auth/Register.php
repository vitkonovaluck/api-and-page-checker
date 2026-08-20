<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\RegisterUserAction;
use App\Enums\SocialProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Register extends Component
{
    public bool $show = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(bool $show = false): void
    {
        $this->show = $show;
    }

    #[On('open-register')]
    public function open(): void
    {
        $this->resetValidation();
        $this->reset(['name', 'email', 'password', 'password_confirmation']);
        $this->show = true;
    }

    #[On('open-login')]
    public function close(): void
    {
        $this->show = false;
        $this->resetValidation();
    }

    public function register(RegisterUserAction $action): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = $action->execute($validated['name'], $validated['email'], $validated['password']);

        Auth::login($user);
        session()->regenerate();

        $this->redirect(route('sites.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.register', [
            'providers' => SocialProvider::configured(),
        ]);
    }
}
