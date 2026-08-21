<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Enums\SocialProvider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Login extends Component
{
    public bool $show = false;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(bool $show = false): void
    {
        $this->show = $show;
        $this->hydrateSessionErrors();
    }

    #[On('open-login')]
    public function open(): void
    {
        $this->resetValidation();
        $this->reset(['email', 'password', 'remember']);
        $this->show = true;
    }

    #[On('open-register')]
    public function close(): void
    {
        $this->show = false;
        $this->resetValidation();
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'login:'.mb_strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds]),
            ]);
        }

        $user = User::query()->where('email', $this->email)->first();

        if ($user !== null && ! $user->usesPasswordLogin()) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.social_only'),
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirectIntended(route('sites.index'));
    }

    public function render(): View
    {
        return view('livewire.auth.login', [
            'providers' => SocialProvider::configured(),
        ]);
    }

    private function hydrateSessionErrors(): void
    {
        $errors = session('errors');
        if (! $errors instanceof ViewErrorBag || ! $errors->has('email')) {
            return;
        }

        $this->addError('email', (string) $errors->first('email'));
        $this->show = true;
    }
}
