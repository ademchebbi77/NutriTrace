<?php

namespace App\Services\Ai;

use App\Models\Transformation;
use App\Models\TransformationCheck;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Plausibility of a transformation (output for the inputs, energy, water, freshness of
 * the sources), checked by the scikit-learn model of the AI service and explained by the
 * LLM (Transformation - Lots adapter). Cached per version of the data and audience.
 */
class TransformationChecker
{
    public function __construct(
        private readonly TransformationAiContext $context,
        private readonly AiClient $client,
    ) {}

    /**
     * Check of a transformation not saved yet (form data), for the transformer.
     *
     * @param  array<string, mixed>  $data  Validated TransformationRequest data.
     */
    public function checkForm(User $transformer, array $data, bool $explain): ?TransformationCheck
    {
        return $this->check($this->context->fromForm($data), TransformationCheck::TRANSFORMER, $explain, $transformer);
    }

    /**
     * Once saved, the transformation keeps the check its transformer saw on the form.
     */
    public function attach(Transformation $transformation): ?TransformationCheck
    {
        $check = TransformationCheck::where([
            'data_version' => $this->context->version($this->context->fromTransformation($transformation)),
            'audience' => TransformationCheck::TRANSFORMER,
        ])->first();

        $check?->update(['transformation_id' => $transformation->id]);

        return $check;
    }

    public function forTransformer(Transformation $transformation): ?TransformationCheck
    {
        return TransformationCheck::where(['transformation_id' => $transformation->id, 'audience' => TransformationCheck::TRANSFORMER])->latest('id')->first();
    }

    /**
     * Admin review of a saved transformation, with the LLM explanation when asked for.
     */
    public function forAdmin(Transformation $transformation, bool $explain = false): ?TransformationCheck
    {
        return $this->check($this->context->fromTransformation($transformation), TransformationCheck::ADMIN, $explain, null, $transformation);
    }

    public function currentForAdmin(Transformation $transformation): ?TransformationCheck
    {
        return TransformationCheck::where([
            'data_version' => $this->context->version($this->context->fromTransformation($transformation)),
            'audience' => TransformationCheck::ADMIN,
        ])->first();
    }

    /**
     * Admin reviews of many transformations, the missing ones computed in one call.
     *
     * @param  Collection<int, Transformation>  $transformations
     * @return Collection<int, TransformationCheck> keyed by transformation id
     */
    public function forList(Collection $transformations): Collection
    {
        $payloads = $transformations->mapWithKeys(fn (Transformation $t) => [$t->id => $this->context->fromTransformation($t)]);
        $versions = $payloads->map(fn (array $payload) => $this->context->version($payload));

        $checks = TransformationCheck::where('audience', TransformationCheck::ADMIN)
            ->whereIn('data_version', $versions->values())
            ->get()
            ->keyBy(fn (TransformationCheck $check) => $versions->search($check->data_version));

        $missing = $payloads->keys()->diff($checks->keys());

        if ($missing->isNotEmpty() && $this->isAvailable()) {
            foreach ($this->client->checkTransformations($payloads->only($missing->all())->values()->all()) ?? [] as $result) {
                if (isset($result['id'], $result['label'])) {
                    $checks[$result['id']] = $this->store($versions[$result['id']], TransformationCheck::ADMIN, $result, null, $result['id']);
                }
            }
        }

        return $checks;
    }

    public function isAvailable(): bool
    {
        return $this->client->has('transformation');
    }

    public function canExplain(): bool
    {
        return $this->client->canChat();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function check(array $payload, string $audience, bool $explain, ?User $user = null, ?Transformation $transformation = null): ?TransformationCheck
    {
        $version = $this->context->version($payload);
        $cached = TransformationCheck::where(['data_version' => $version, 'audience' => $audience])->first();

        if ($cached && (! $explain || $cached->explanation)) {
            return $cached;
        }

        $result = $this->client->checkTransformation($payload, $explain, $audience);

        return isset($result['label']) ? $this->store($version, $audience, $result, $user, $transformation?->id ?? $cached?->transformation_id) : $cached;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function store(string $version, string $audience, array $result, ?User $user, ?int $transformationId): TransformationCheck
    {
        return TransformationCheck::updateOrCreate(
            ['data_version' => $version, 'audience' => $audience],
            [
                'transformation_id' => $transformationId,
                'user_id' => $user?->id,
                'label' => $result['label'],
                'is_anomaly' => (bool) $result['is_anomaly'],
                'risk' => (float) ($result['risk'] ?? 0),
                'confidence' => (float) ($result['confidence'] ?? 0),
                'expected' => $result['expected'] ?? [],
                'reasons' => array_values($result['reasons'] ?? []),
                'explanation' => $result['explanation'] ?? null,
                'model' => $result['model'] ?? null,
            ],
        );
    }
}
