<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\ExtensionAgentLoginAction;
use App\Actions\FindOrCreateFromSocialAction;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

final class SocialAuthController extends Controller
{
    public function __construct(
        private ExtensionAgentLoginAction $extensionLogin,
    ) {}

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
            return $this->failedSocialRedirect($request, $exception->errors());
        } catch (InvalidStateException|Throwable) {
            return $this->failedSocialRedirect($request, [
                'email' => [__('auth.social_failed')],
            ]);
        }

        Auth::login($user);
        $ticket = $request->session()->pull(ExtensionAgentLoginAction::SESSION_KEY);
        $request->session()->regenerate();

        if (! is_string($ticket) || $ticket === '') {
            return redirect()->intended(route('sites.index'));
        }

        try {
            $this->extensionLogin->complete($user, $ticket, $request->ip());
        } catch (RuntimeException) {
            return redirect()
                ->route('extension.connected')
                ->withErrors(['email' => __('agent.extension_login_expired')]);
        }

        return redirect()->route('extension.connected');
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function failedSocialRedirect(Request $request, array $errors): RedirectResponse
    {
        $ticket = $request->session()->pull(ExtensionAgentLoginAction::SESSION_KEY);

        if (is_string($ticket) && $ticket !== '') {
            $this->extensionLogin->fail($ticket, __('auth.social_failed'));

            return redirect()
                ->route('extension.connected')
                ->withErrors($errors);
        }

        return redirect()
            ->route('login')
            ->withErrors($errors);
    }

    private function resolveProvider(string $provider): SocialProvider
    {
        $socialProvider = SocialProvider::tryFrom($provider);

        abort_if($socialProvider === null || ! $socialProvider->isConfigured(), 404);

        return $socialProvider;
    }
}
