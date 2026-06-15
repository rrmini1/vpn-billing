<?php

namespace Tests\Unit;

use App\Exceptions\Marzban\MarzbanApiException;
use App\Services\Marzban\MarzbanService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarzbanServiceTest extends TestCase
{
    public function test_create_user_sends_vless_reality_payload_and_returns_response(): void
    {
        Http::fake([
            'https://panel.cors-port.ru/api/admin/token' => Http::response([
                'access_token' => 'test-token',
                'token_type' => 'bearer',
            ]),
            'https://panel.cors-port.ru/api/user' => Http::response([
                'username' => 'test_user',
                'status' => 'active',
                'data_limit' => 1073741824,
                'subscription_url' => 'https://panel.cors-port.ru/sub/test-token/',
                'links' => ['nl', 'ca', 'ru'],
            ]),
        ]);

        $response = app(MarzbanService::class)->createUser('test_user', 1073741824, 'Trial user');

        $this->assertSame('test_user', $response['username']);
        $this->assertSame(1073741824, $response['data_limit']);
        $this->assertSame('https://panel.cors-port.ru/sub/test-token/', $response['subscription_url']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://panel.cors-port.ru/api/admin/token'
            && $request['username'] === 'test-marzban-admin'
            && $request['password'] === 'test-marzban-password');

        Http::assertSent(function (Request $request): bool {
            $payload = json_decode($request->body(), true);

            return $request->url() === 'https://panel.cors-port.ru/api/user'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $payload['username'] === 'test_user'
                && $payload['status'] === 'active'
                && $payload['data_limit'] === 1073741824
                && $payload['data_limit_reset_strategy'] === 'no_reset'
                && $payload['expire'] === null
                && $payload['proxies'] === ['vless' => []]
                && $payload['inbounds'] === ['vless' => ['VLESS TCP REALITY']]
                && $payload['note'] === 'Trial user';
        });
    }

    public function test_get_user_returns_marzban_response(): void
    {
        Http::fake([
            'https://panel.cors-port.ru/api/admin/token' => Http::response(['access_token' => 'test-token']),
            'https://panel.cors-port.ru/api/user/test_user' => Http::response([
                'username' => 'test_user',
                'used_traffic' => 0,
                'subscription_url' => 'https://panel.cors-port.ru/sub/test-token/',
            ]),
        ]);

        $response = app(MarzbanService::class)->getUser('test_user');

        $this->assertSame('test_user', $response['username']);
        $this->assertSame(0, $response['used_traffic']);
    }

    public function test_update_user_limit_sends_limit_payload(): void
    {
        Http::fake([
            'https://panel.cors-port.ru/api/admin/token' => Http::response(['access_token' => 'test-token']),
            'https://panel.cors-port.ru/api/user/test_user' => Http::response([
                'username' => 'test_user',
                'data_limit' => 2147483648,
            ]),
        ]);

        $response = app(MarzbanService::class)->updateUserLimit('test_user', 2147483648);

        $this->assertSame(2147483648, $response['data_limit']);

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'https://panel.cors-port.ru/api/user/test_user'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['data_limit'] === 2147483648
            && $request['data_limit_reset_strategy'] === 'no_reset');
    }

    public function test_delete_user_sends_delete_request(): void
    {
        Http::fake([
            'https://panel.cors-port.ru/api/admin/token' => Http::response(['access_token' => 'test-token']),
            'https://panel.cors-port.ru/api/user/test_user' => Http::response([]),
        ]);

        app(MarzbanService::class)->deleteUser('test_user');

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'https://panel.cors-port.ru/api/user/test_user'
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_failed_marzban_response_throws_api_exception(): void
    {
        Http::fake([
            'https://panel.cors-port.ru/api/admin/token' => Http::response(['access_token' => 'test-token']),
            'https://panel.cors-port.ru/api/user' => Http::response(['detail' => 'Validation error'], 422),
        ]);

        try {
            app(MarzbanService::class)->createUser('test_user', 1073741824);
            $this->fail('Expected MarzbanApiException was not thrown.');
        } catch (MarzbanApiException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertSame(['detail' => 'Validation error'], $exception->response());
        }
    }

    public function test_missing_access_token_throws_api_exception(): void
    {
        Http::fake([
            'https://panel.cors-port.ru/api/admin/token' => Http::response(['token_type' => 'bearer']),
        ]);

        $this->expectException(MarzbanApiException::class);
        $this->expectExceptionMessage('Marzban authentication response did not contain an access token.');

        app(MarzbanService::class)->getUser('test_user');
    }
}
