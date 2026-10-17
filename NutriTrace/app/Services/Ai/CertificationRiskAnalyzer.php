<?php

namespace App\Services\Ai;

use App\Models\Certification;
use App\Models\CertificationAnalysis;
use Illuminate\Support\Collection;

/**
 * Risk that a certificate is not genuine (scikit-learn model of the AI service, which
 * also reads the PDF proof), with an explanation for the admin written by the LLM on
 * demand. Cached per version of the certificate's data and proof.
 */
class CertificationRiskAnalyzer
{
    public function __construct(
        private readonly DistributionAiContext $context,
        private readonly AiClient $client,
    ) {}

    public function current(Certification $certification): ?CertificationAnalysis
    {
        return CertificationAnalysis::where('certification_id', $certification->id)
            ->where('data_version', $this->version($certification))
            ->latest('id')
            ->first();
    }

    /**
     * Score (and reasons) of the certificate, computed if missing. The LLM explanation is
     * added when asked for: it takes a few seconds, the score alone is instant.
     */
    public function analyze(Certification $certification, bool $explain = false): ?CertificationAnalysis
    {
        $payload = $this->context->forCertification($certification);
        $version = $this->context->version($payload);
        $cached = CertificationAnalysis::where(['certification_id' => $certification->id, 'data_version' => $version])->first();

        if ($cached && (! $explain || $cached->explanation)) {
            return $cached;
        }

        $result = $this->client->certificationRisk($payload, $explain);

        return isset($result['level']) ? $this->store($certification, $version, $result) : $cached;
    }

    /**
     * Current analyses of many certificates, scoring the missing ones in one call.
     *
     * @param  Collection<int, Certification>  $certifications
     * @return Collection<int, CertificationAnalysis> keyed by certification id
     */
    public function forList(Collection $certifications): Collection
    {
        $payloads = $certifications->mapWithKeys(fn (Certification $certification) => [$certification->id => $this->context->forCertification($certification)]);
        $versions = $payloads->map(fn (array $payload) => $this->context->version($payload));

        $analyses = CertificationAnalysis::whereIn('certification_id', $certifications->modelKeys())
            ->whereIn('data_version', $versions->values())
            ->get()
            ->filter(fn (CertificationAnalysis $analysis) => $versions[$analysis->certification_id] === $analysis->data_version)
            ->keyBy('certification_id');

        $missing = $payloads->keys()->diff($analyses->keys());

        if ($missing->isNotEmpty() && $this->isAvailable()) {
            foreach ($this->client->certificationRiskBatch($payloads->only($missing->all())->values()->all()) ?? [] as $result) {
                if (isset($result['id'], $result['level'])) {
                    $analyses[$result['id']] = $this->store($certifications->find($result['id']), $versions[$result['id']], $result);
                }
            }
        }

        return $analyses;
    }

    public function isAvailable(): bool
    {
        return $this->client->has('certification');
    }

    public function canExplain(): bool
    {
        return $this->client->canChat();
    }

    private function version(Certification $certification): string
    {
        return $this->context->version($this->context->forCertification($certification));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function store(Certification $certification, string $version, array $result): CertificationAnalysis
    {
        return CertificationAnalysis::updateOrCreate(
            ['certification_id' => $certification->id, 'data_version' => $version],
            [
                'level' => $result['level'],
                'risk' => (float) $result['risk'],
                'confidence' => (float) ($result['confidence'] ?? 0),
                'reasons' => array_values($result['reasons'] ?? []),
                'document_readable' => (bool) ($result['document_readable'] ?? false),
                'explanation' => $result['explanation'] ?? null,
                'model' => $result['model'] ?? null,
            ],
        );
    }
}
