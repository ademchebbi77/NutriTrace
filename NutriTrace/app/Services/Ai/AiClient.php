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
     * Whether a training run holds the GPU (the LLM is unloaded meanwhile).
     */
    public function isTraining(): bool
    {
        return (bool) ($this->health()['training'] ?? false);
    }

    /**
     * Whether one of the scikit-learn models is loaded: "anomaly", "certification" or "reception".
     */
    public function has(string $model): bool
    {
        return (bool) ($this->health()[$model] ?? false);
    }

    public function forgetHealth(): void
    {
        Cache::forget(self::HEALTH_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
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
     * @param  array<string, mixed>  $context  See DistributionAiContext::forDistributor().
     * @return ?array{answer: string, model: string}
     */
    public function chatDistributor(array $context, string $question): ?array
    {
        return $this->post('/distribution/chat', ['context' => $context, 'question' => $question]);
    }

    /**
     * @param  array<string, mixed>  $reception  See DistributionAiContext::forReception().
     * @return ?array{decision: string, confidence: float, reasons: list<string>, explanation: ?string, transport_label: ?string, model: string}
     */
    public function checkReception(array $reception): ?array
    {
        return $this->post('/distribution/reception', ['reception' => $reception]);
    }

    /**
     * @param  array<string, mixed>  $certificate  See DistributionAiContext::forCertification().
     * @return ?array{level: string, risk: float, confidence: float, reasons: list<string>, document_readable: bool, explanation: ?string, model: string}
     */
    public function certificationRisk(array $certificate, bool $explain = false): ?array
    {
        return $this->post('/certification/risk', ['certificate' => $certificate, 'explain' => $explain]);
    }

    /**
     * @param  list<array<string, mixed>>  $certificates
     * @return ?list<array<string, mixed>>
     */
    public function certificationRiskBatch(array $certificates): ?array
    {
        return $this->post('/certification/risk/batch', ['certificates' => $certificates])['results'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $transformation  See TransformationAiContext.
     * @return ?array{is_anomaly: bool, label: string, confidence: float, risk: float, reasons: list<string>, expected: array<string, mixed>, explanation: ?string, model: string}
     */
    public function checkTransformation(array $transformation, bool $explain, string $audience): ?array
    {
        return $this->post('/transformation/check', ['transformation' => $transformation, 'explain' => $explain, 'audience' => $audience]);
    }

    /**
     * @param  list<array<string, mixed>>  $transformations
     * @return ?list<array<string, mixed>>
     */
    public function checkTransformations(array $transformations): ?array
    {
        return $this->post('/transformation/check/batch', ['transformations' => $transformations])['results'] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $lots  See TransformationAiContext::forLotExpiry().
     * @return ?list<array<string, mixed>>
     */
    public function lotsExpiry(array $lots): ?array
    {
        return $this->post('/lots/expiry/batch', ['lots' => $lots])['results'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $declaration  See ImpactAiContext::forDeclaration().
     * @return ?array{is_anomaly: bool, label: string, confidence: float, findings: list<array<string, mixed>>, reasons: list<string>, explanation: ?string, model: string}
     */
    public function checkImpact(array $declaration, bool $explain): ?array
    {
        return $this->post('/impact/check', ['declaration' => $declaration, 'explain' => $explain]);
    }

    /**
     * @param  array<string, mixed>  $lot  See ImpactAiContext::forAdvice().
     * @return ?array{score: ?int, grade: ?string, target: ?string, levers: list<string>, explanation: string, model: string}
     */
    public function impactAdvice(array $lot): ?array
    {
        return $this->post('/impact/advice', ['lot' => $lot]);
    }

    /**
     * @param  array<string, mixed>  $review  See ImpactAiContext::forReview().
     * @return ?array{label: string, confidence: float, flagged: bool, reasons: list<string>, explanation: ?string, model: string}
     */
    public function moderateReview(array $review, bool $explain = false): ?array
    {
        return $this->post('/reviews/moderate', ['review' => $review, 'explain' => $explain]);
    }

    /**
     * Administration of the service (admin page): returns [ok, body]. Unlike the other
     * calls it reports why a request was refused (a run already in progress...).
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: bool, 1: array<string, mixed>}
     */
    public function admin(string $method, string $path, array $payload = []): array
    {
        try {
            $response = $this->request(15)->connectTimeout(2)->send($method, $path, $method === 'GET' ? [] : ['json' => $payload]);
        } catch (ConnectionException) {
            return [false, ['detail' => __('ai.admin.service_down')]];
        }

        if ($method !== 'GET') {
            // Starting or stopping a run loads or unloads models.
            $this->forgetHealth();
        }

        return [$response->successful(), (array) $response->json()];
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
        // Web requests only: in a console process (commands, tests) it would limit the whole run.
        if (! app()->runningInConsole()) {
            set_time_limit(config('ai.timeout') + 15);
        }

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
