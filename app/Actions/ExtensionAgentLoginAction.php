<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\IssueAgentTokenDTO;
use App\Enums\ExtensionLoginStatus;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;

final class ExtensionAgentLoginAction
{
    public const SESSION_KEY = 'extension_login_ticket';

    public function __construct(
        private Repository $cache,
        private IssueAgentTokenAction $issueToken,
    ) {}

    public function start(string $name, ?string $hostname): string
    {
        $ticket = bin2hex(random_bytes(32));

        $this->cache->put($this->cacheKey($ticket), [
            'name' => $name,
            'hostname' => $hostname,
            'status' => ExtensionLoginStatus::Pending->value,
            'token' => null,
            'message' => null,
        ], $this->ttlSeconds());

        return $ticket;
    }

    public function isPending(string $ticket): bool
    {
        $payload = $this->payload($ticket);

        return $payload !== null
            && $payload['status'] === ExtensionLoginStatus::Pending->value;
    }

    public function complete(User $user, string $ticket, ?string $ip): void
    {
        $payload = $this->requirePending($ticket);
        $issued = $this->issueToken->execute($user, new IssueAgentTokenDTO(
            name: $payload['name'],
            hostname: $payload['hostname'],
            ip: $ip,
        ));

        $this->cache->put($this->cacheKey($ticket), [
            'name' => $payload['name'],
            'hostname' => $payload['hostname'],
            'status' => ExtensionLoginStatus::Ready->value,
            'token' => $issued['plainTextToken'],
            'message' => null,
        ], $this->ttlSeconds());
    }

    public function fail(string $ticket, string $message): void
    {
        $payload = $this->payload($ticket);

        if ($payload === null || $payload['status'] !== ExtensionLoginStatus::Pending->value) {
            return;
        }

        $this->cache->put($this->cacheKey($ticket), [
            'name' => $payload['name'],
            'hostname' => $payload['hostname'],
            'status' => ExtensionLoginStatus::Failed->value,
            'token' => null,
            'message' => $message,
        ], $this->ttlSeconds());
    }

    /**
     * @return array{status: string, token: ?string, message: ?string}
     */
    public function consume(string $ticket): array
    {
        $payload = $this->payload($ticket);

        if ($payload === null) {
            throw new RuntimeException('expired');
        }

        $status = ExtensionLoginStatus::from($payload['status']);

        if ($status === ExtensionLoginStatus::Pending) {
            return [
                'status' => $status->value,
                'token' => null,
                'message' => null,
            ];
        }

        $this->cache->forget($this->cacheKey($ticket));

        return [
            'status' => $status->value,
            'token' => $payload['token'],
            'message' => $payload['message'],
        ];
    }

    /**
     * @return array{name: string, hostname: ?string, status: string, token: ?string, message: ?string}|null
     */
    private function payload(string $ticket): ?array
    {
        $payload = $this->cache->get($this->cacheKey($ticket));

        if (! is_array($payload) || ! isset($payload['status'], $payload['name'])) {
            return null;
        }

        return [
            'name' => (string) $payload['name'],
            'hostname' => isset($payload['hostname']) && is_string($payload['hostname'])
                ? $payload['hostname']
                : null,
            'status' => (string) $payload['status'],
            'token' => isset($payload['token']) && is_string($payload['token'])
                ? $payload['token']
                : null,
            'message' => isset($payload['message']) && is_string($payload['message'])
                ? $payload['message']
                : null,
        ];
    }

    /**
     * @return array{name: string, hostname: ?string, status: string, token: ?string, message: ?string}
     */
    private function requirePending(string $ticket): array
    {
        $payload = $this->payload($ticket);

        if ($payload === null || $payload['status'] !== ExtensionLoginStatus::Pending->value) {
            throw new RuntimeException('expired');
        }

        return $payload;
    }

    private function cacheKey(string $ticket): string
    {
        return 'agent-extension-login:'.$ticket;
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) config('checking.extension_login_ttl_seconds', 300));
    }
}
