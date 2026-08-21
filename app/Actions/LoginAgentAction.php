<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\IssueAgentTokenDTO;
use App\Models\CheckAgent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class LoginAgentAction
{
    public function __construct(
        private readonly IssueAgentTokenAction $issueToken,
    ) {}

    /**
     * @return array{user: User, agent: CheckAgent, plainTextToken: string}
     */
    public function execute(string $email, string $password, IssueAgentTokenDTO $dto): array
    {
        $user = User::query()->where('email', $email)->first();
        $this->assertExistingPasswordUser($user, $password);
        assert($user instanceof User);

        $issued = $this->issueToken->execute($user, $dto);

        return [
            'user' => $user,
            'agent' => $issued['agent'],
            'plainTextToken' => $issued['plainTextToken'],
        ];
    }

    private function assertExistingPasswordUser(?User $user, string $password): void
    {
        if ($user !== null && ! $user->usesPasswordLogin()) {
            throw new HttpException(403, __('agent.password_login_required'));
        }

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            throw new HttpException(401, __('agent.invalid_credentials'));
        }
    }
}
