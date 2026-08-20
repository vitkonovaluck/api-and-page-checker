<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\FindOrCreateFromSocialAction;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

final class SocialAuthController extends Controller
{
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        $socialProvider = $this->resolveProvider($provider);
        $driver = Socialite::driver($socialProvider->driver());
        $scopes = $socialProvider->scopes();

        if ($scopes !== []) {
            $driver = $driver->scopes($scopes);
        }

        return $driver->redirect();
    }

    public function callback(string $provider, Request $request, FindOrCreateFromSocialAction $action): RedirectResponse
    {
        $socialProvider = $this->resolveProvider($provider);

        try {
            $oauthUser = Socialite::driver($socialProvider->driver())->user();
            $user = $action->execute(
                $socialProvider,
                (string) $oauthUser->getId(),
                $oauthUser->getEmail(),
                $oauthUser->getName(),
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        } catch (InvalidStateException|Throwable) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('auth.social_failed')]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('sites.index'));
    }

    private function resolveProvider(string $provider): SocialProvider
    {
        $socialProvider = SocialProvider::tryFrom($provider);

        abort_if($socialProvider === null || ! $socialProvider->isConfigured(), 404);

        return $socialProvider;
    }
}
