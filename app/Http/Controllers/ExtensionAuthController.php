<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ExtensionAgentLoginAction;
use App\Enums\SocialProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ExtensionAuthController extends Controller
{
    public function __invoke(
        string $provider,
        Request $request,
        ExtensionAgentLoginAction $login,
    ): RedirectResponse {
        $socialProvider = SocialProvider::tryFrom($provider);
        abort_if($socialProvider === null || ! $socialProvider->isConfigured(), 404);

        $ticket = $request->string('ticket')->toString();
        abort_unless($login->isPending($ticket), 404);

        $request->session()->put(ExtensionAgentLoginAction::SESSION_KEY, $ticket);

        $user = $request->user();
        if ($user instanceof User) {
            try {
                $login->complete($user, $ticket, $request->ip());
            } catch (RuntimeException) {
                return redirect()
                    ->route('extension.connected')
                    ->withErrors(['email' => __('agent.extension_login_expired')]);
            }

            return redirect()->route('extension.connected');
        }

        return redirect()->route('auth.social.redirect', $socialProvider->value);
    }
}
