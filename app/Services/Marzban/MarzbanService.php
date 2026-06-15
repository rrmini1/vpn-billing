<?php

namespace App\Services\Marzban;

use App\Exceptions\Marzban\MarzbanApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MarzbanService
{
    public function createUser(string $username, int $dataLimitBytes, ?string $note = null): array
    {
        return $this->request('post', '/api/user', [
            'username' => $username,
            'status' => 'active',
            'data_limit' => $dataLimitBytes,
            'data_limit_reset_strategy' => $this->dataLimitResetStrategy(),
            'expire' => null,
            'proxies' => [
                $this->proxyType() => (object) [],
            ],
            'inbounds' => [
                $this->proxyType() => [
                    $this->inbound(),
                ],
            ],
            'note' => $note,
        ]);
    }

    public function getUser(string $username): array
    {
        return $this->request('get', '/api/user/'.rawurlencode($username));
    }

    public function updateUserLimit(string $username, int $dataLimitBytes): array
    {
        return $this->request('put', '/api/user/'.rawurlencode($username), [
            'data_limit' => $dataLimitBytes,
            'data_limit_reset_strategy' => $this->dataLimitResetStrategy(),
        ]);
    }

    public function deleteUser(string $username): void
    {
        $this->request('delete', '/api/user/'.rawurlencode($username));
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $response = $this->authenticatedRequest()->{$method}($path, $payload ?? []);

        if ($response->failed()) {
            $this->throwApiException('Marzban API request failed.', $response);
        }

        return $response->json() ?? [];
    }

    private function authenticatedRequest(): PendingRequest
    {
        return $this->baseRequest()->withToken($this->accessToken());
    }

    private function accessToken(): string
    {
        $response = $this->baseRequest()
            ->asForm()
            ->post('/api/admin/token', [
                'username' => config('marzban.username'),
                'password' => config('marzban.password'),
            ]);

        if ($response->failed()) {
            $this->throwApiException('Marzban authentication failed.', $response);
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new MarzbanApiException('Marzban authentication response did not contain an access token.');
        }

        return $token;
    }

    private function baseRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout((int) config('marzban.timeout', 10));
    }

    private function baseUrl(): string
    {
        $baseUrl = config('marzban.base_url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new MarzbanApiException('Marzban base URL is not configured.');
        }

        return rtrim($baseUrl, '/');
    }

    private function proxyType(): string
    {
        return (string) config('marzban.proxy_type', 'vless');
    }

    private function inbound(): string
    {
        return (string) config('marzban.inbound', 'VLESS TCP REALITY');
    }

    private function dataLimitResetStrategy(): string
    {
        return (string) config('marzban.data_limit_reset_strategy', 'no_reset');
    }

    private function throwApiException(string $message, Response $response): never
    {
        throw new MarzbanApiException(
            $message,
            $response->status(),
            $response->json(),
        );
    }
}
