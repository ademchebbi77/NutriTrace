<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client of the local Python AI service (ai/server.py). Every call fails
 * softly: null means "no answer", and the callers fall back on the cache.
 */
class AiClient
{
    private const HEALTH_KEY = 'ai.service.up';

    /**
     * Whether the service is up with at least one model loaded, checked at most once a minute.
     */
    public function available(): bool
    {
        return (bool) ($this->health()['ready'] ?? false);
    }

    /**
     * Whether the LLM is loaded (the service can also run with the anomaly model only).
     */
    public function canChat(): bool
    {
        return $this->available() && ($this->health()['chat'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private function health(): array
    {
        if (! config('ai.enabled')) {
            return [];
        }

        return Cache::remember(self::HEALTH_KEY, config('ai.health_ttl'), function () {
            try {
                // Short: this check runs while a page is being built.
                return (array) $this->request(2)->connectTimeout(1)->get('/health')->json();
            } catch (ConnectionException) {
                return [];
            }
        });
    }

    /**
     * @param  array<string, mixed>  $context  See AiContext::forLot().
     * @return ?array{answer: string, model: string}
     */
    public function chat(array $context, string $question): ?array
    {
        return $this->post('/chat', ['context' => $context, 'question' => $question]);
    }

    /**
     * @param  array<string, mixed>  $transport  See AiContext::forTransport().
     * @return ?array{is_anomaly: bool, label: string, confidence: float, reasons: list<string>, explanation: ?string, model: string}
     */
    public function analyzeTransport(array $transport): ?array
    {
        return $this->post('/transport/analyze', ['transport' => $transport]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return ?array<string, mixed>
     */
    private function post(string $path, array $payload): ?array
    {
        if (! $this->available()) {
            return null;
        }

        // PHP's own limit (30 s by default with `php artisan serve`) would kill the request
        // before the HTTP timeout: a slow generation must end with "no answer", not a fatal error.
        set_time_limit(config('ai.timeout') + 15);

        try {
            return $this->request(config('ai.timeout'))->post($path, $payload)->throw()->json();
        } catch (ConnectionException $exception) {
            Log::warning('AI service unreachable', ['path' => $path, 'error' => $exception->getMessage()]);
            // Do not wait for the next health check to stop calling a service that is down.
            Cache::put(self::HEALTH_KEY, [], config('ai.health_ttl'));

            return null;
        } catch (RequestException $exception) {
            // Up but refusing this call (e.g. LLM not loaded): the other feature may still work.
            Log::warning('AI service call failed', ['path' => $path, 'status' => $exception->response->status()]);

            return null;
        }
    }

    private function request(int $timeout): PendingRequest
    {
        return Http::baseUrl(config('ai.url'))
            ->acceptJson()
            ->timeout($timeout)
            ->withHeaders(array_filter(['X-AI-Token' => config('ai.token')]));
    }
}
