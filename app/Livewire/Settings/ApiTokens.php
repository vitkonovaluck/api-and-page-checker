<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\IssueApiTokenAction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;

final class ApiTokens extends Component
{
    public string $name = '';

    public ?string $plainTextToken = null;

    public function create(IssueApiTokenAction $issueToken): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $issued = $issueToken->execute($this->currentUser(), $validated['name']);
        $this->plainTextToken = $issued['plainTextToken'];
        $this->reset('name');
        $this->resetValidation();
    }

    public function revoke(int $tokenId): void
    {
        $token = $this->currentUser()->tokens()->whereKey($tokenId)->firstOrFail();

        if (! $this->isApiToken($token)) {
            abort(404);
        }

        $token->delete();
        $this->plainTextToken = null;
    }

    public function render(): View
    {
        /** @var Collection<int, PersonalAccessToken> $tokens */
        $tokens = $this->currentUser()
            ->tokens()
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PersonalAccessToken $token): bool => $this->isApiToken($token))
            ->values();

        return view('livewire.settings.api-tokens', [
            'tokens' => $tokens,
        ]);
    }

    private function isApiToken(PersonalAccessToken $token): bool
    {
        $abilities = $token->abilities ?? [];

        return in_array('api', $abilities, true);
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
