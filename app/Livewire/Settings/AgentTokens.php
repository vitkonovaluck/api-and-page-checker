<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\IssueAgentTokenAction;
use App\DTOs\IssueAgentTokenDTO;
use App\Models\CheckAgent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

final class AgentTokens extends Component
{
    public string $name = '';

    public string $hostname = '';

    public string $region = '';

    public ?string $plainTextToken = null;

    public function create(IssueAgentTokenAction $issueToken): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:64'],
        ]);

        $issued = $issueToken->execute(
            $this->currentUser(),
            new IssueAgentTokenDTO(
                name: $validated['name'],
                hostname: $validated['hostname'] !== '' ? $validated['hostname'] : null,
                ip: request()->ip(),
                region: $validated['region'] !== '' ? $validated['region'] : null,
            ),
        );

        $this->plainTextToken = $issued['plainTextToken'];
        $this->reset(['name', 'hostname', 'region']);
        $this->resetValidation();
    }

    public function revoke(CheckAgent $agent): void
    {
        $this->authorize('delete', $agent);

        $user = $this->currentUser();
        if ($agent->personal_access_token_id !== null) {
            $user->tokens()->whereKey($agent->personal_access_token_id)->delete();
        }

        $agent->delete();
        $this->plainTextToken = null;
    }

    public function render(): View
    {
        /** @var Collection<int, CheckAgent> $agents */
        $agents = $this->currentUser()
            ->checkAgents()
            ->orderByDesc('id')
            ->get();

        return view('livewire.settings.agent-tokens', [
            'agents' => $agents,
        ]);
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
