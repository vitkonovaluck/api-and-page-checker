<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Site;

class InspectSslCertificateAction
{
    public function execute(string $host, int $port = 443, int $timeout = 10): ?int
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $client = @stream_socket_client(
            'ssl://'.$host.':'.$port,
            $errorNumber,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($certificate === null) {
            return null;
        }

        $parsed = openssl_x509_parse($certificate);

        if (! is_array($parsed) || ! isset($parsed['validTo_time_t'])) {
            return null;
        }

        return (int) $parsed['validTo_time_t'];
    }

    public function hostFromSite(Site $site): ?string
    {
        $host = parse_url($site->base_url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
