<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for Cohere AI API.
 * Used for external AI-powered analysis separate from the local Python service.
 */
class CohereClient
{
    /**
     * Send a chat completion request to Cohere API.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return ?array{text: string, finish_reason: string, id: string}
     */
    public function chat(array $messages, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('Cohere API key not configured');

            return null;
        }

        try {
            $response = $this->request()->post('/chat', array_filter([
                'model' => config('services.cohere.model'),
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.3,
                'max_tokens' => $options['max_tokens'] ?? 2000,
                'response_format' => $options['response_format'] ?? null,
            ]))->throw();

            $data = $response->json();

            // Extract the message content from the response
            $text = $data['message']['content'][0]['text'] ?? null;

            if (! $text) {
                Log::warning('Cohere API returned empty response', ['data' => $data]);

                return null;
            }

            return [
                'text' => $text,
                'finish_reason' => $data['finish_reason'] ?? 'unknown',
                'id' => $data['id'] ?? 'unknown',
            ];
        } catch (ConnectionException $exception) {
            Log::error('Cohere API connection failed', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        } catch (RequestException $exception) {
            Log::error('Cohere API request failed', [
                'status' => $exception->response->status(),
                'body' => $exception->response->body(),
            ]);

            return null;
        } catch (\Exception $exception) {
            Log::error('Cohere API unexpected error', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if Cohere API is configured.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.cohere.api_key'));
    }

    /**
     * Create a configured HTTP request instance.
     */
    private function request(): PendingRequest
    {
        $http = Http::baseUrl(config('services.cohere.base_url'))
            ->withHeaders([
                'Authorization' => 'Bearer '.config('services.cohere.api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(config('services.cohere.timeout'))
            ->retry(2, 1000);

        // Disable SSL verification for local development (Windows SSL certificate issue)
        if (app()->environment('local')) {
            $http->withOptions(['verify' => false]);
        }

        return $http;
    }
}
