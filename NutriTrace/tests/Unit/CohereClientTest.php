<?php

namespace Tests\Unit;

use App\Services\Ai\CohereClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CohereClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure Cohere for tests
        config([
            'services.cohere.api_key' => 'test-api-key',
            'services.cohere.model' => 'command-r-08-2024',
            'services.cohere.base_url' => 'https://api.cohere.com/v2',
            'services.cohere.timeout' => 30,
        ]);
    }

    public function test_chat_sends_correct_request_to_cohere_api(): void
    {
        Http::fake([
            'api.cohere.com/*' => Http::response([
                'id' => 'chat-123',
                'finish_reason' => 'COMPLETE',
                'message' => [
                    'content' => [
                        ['text' => 'Test response from Cohere'],
                    ],
                ],
            ], 200),
        ]);

        $client = new CohereClient;

        $messages = [
            ['role' => 'user', 'content' => 'Test question'],
        ];

        $response = $client->chat($messages);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cohere.com/v2/chat' &&
                   $request['model'] === 'command-r-08-2024' &&
                   $request['messages'][0]['role'] === 'user' &&
                   $request->hasHeader('Authorization', 'Bearer test-api-key');
        });

        $this->assertNotNull($response);
        $this->assertEquals('Test response from Cohere', $response['text']);
        $this->assertEquals('COMPLETE', $response['finish_reason']);
        $this->assertEquals('chat-123', $response['id']);
    }

    public function test_chat_includes_optional_parameters(): void
    {
        Http::fake([
            'api.cohere.com/*' => Http::response([
                'id' => 'chat-123',
                'finish_reason' => 'COMPLETE',
                'message' => [
                    'content' => [
                        ['text' => 'Response'],
                    ],
                ],
            ], 200),
        ]);

        $client = new CohereClient;

        $client->chat(
            [['role' => 'user', 'content' => 'Test']],
            [
                'temperature' => 0.5,
                'max_tokens' => 1000,
            ]
        );

        Http::assertSent(function ($request) {
            return $request['temperature'] === 0.5 &&
                   $request['max_tokens'] === 1000;
        });
    }

    public function test_chat_returns_null_when_api_key_not_configured(): void
    {
        config(['services.cohere.api_key' => null]);

        $client = new CohereClient;

        $response = $client->chat([
            ['role' => 'user', 'content' => 'Test'],
        ]);

        $this->assertNull($response);
        Http::assertNothingSent();
    }

    public function test_chat_returns_null_on_connection_error(): void
    {
        Http::fake([
            'api.cohere.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timeout'),
        ]);

        $client = new CohereClient;

        $response = $client->chat([
            ['role' => 'user', 'content' => 'Test'],
        ]);

        $this->assertNull($response);
    }

    public function test_chat_returns_null_on_api_error(): void
    {
        Http::fake([
            'api.cohere.com/*' => Http::response([
                'error' => 'Invalid request',
            ], 400),
        ]);

        $client = new CohereClient;

        $response = $client->chat([
            ['role' => 'user', 'content' => 'Test'],
        ]);

        $this->assertNull($response);
    }

    public function test_chat_returns_null_when_response_has_no_text(): void
    {
        Http::fake([
            'api.cohere.com/*' => Http::response([
                'id' => 'chat-123',
                'finish_reason' => 'COMPLETE',
                'message' => [
                    'content' => [],
                ],
            ], 200),
        ]);

        $client = new CohereClient;

        $response = $client->chat([
            ['role' => 'user', 'content' => 'Test'],
        ]);

        $this->assertNull($response);
    }

    public function test_is_configured_returns_true_when_api_key_is_set(): void
    {
        config(['services.cohere.api_key' => 'test-key']);

        $client = new CohereClient;

        $this->assertTrue($client->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_is_empty(): void
    {
        config(['services.cohere.api_key' => '']);

        $client = new CohereClient;

        $this->assertFalse($client->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_is_null(): void
    {
        config(['services.cohere.api_key' => null]);

        $client = new CohereClient;

        $this->assertFalse($client->isConfigured());
    }
}
