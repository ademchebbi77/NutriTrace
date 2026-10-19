<?php

namespace App\Services\Ai;

use App\Enums\EventType;
use App\Models\Certification;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\Product;
use App\Models\TraceabilityEvent;
use App\Models\User;
use App\Services\CertificationService;
use App\Services\LotInsightsBuilder;
use Illuminate\Support\Facades\Storage;

/**
 * Data handed to the local AI for the Distribution - Certification module. Raw data
 * only: totals, rankings and features are computed on the Python side, the same way
 * for training and for answers (ai/nutritrace_ai/stock.py, reception.py, certificates.py).
 */
class DistributionAiContext
{
    public function __construct(
        private readonly LotInsightsBuilder $insights,
        private readonly AiContext $context,
    ) {}

    /**
     * A distributor's receptions, stock and sales, for the distributor assistant.
     *
     * @return array<string, mixed>
     */
    public function forDistributor(User $distributor): array
    {
        $distributions = Distribution::where('distributor_id', $distributor->id)
            ->with(['lot.product', 'sender.organization'])
            ->oldest('id')
            ->get();

        $sales = TraceabilityEvent::where('event_type', EventType::SALE)
            ->where('actor_id', $distributor->id)
            ->with('lot.product')
            ->oldest('occurred_at')
            ->get();

        return [
            'today' => today()->toDateString(),
            'distributor' => ['name' => $distributor->displayName(), 'city' => $distributor->organization?->city],
            'distributions' => $distributions->map(fn (Distribution $distribution) => [
                'lot' => $distribution->lot->lot_number,
                'product' => $distribution->lot->product->name,
                'unit' => $distribution->lot->unit->symbol(),
                'status' => $distribution->status->value,
                'status_label' => $distribution->status->label(),
                'lot_status_label' => $distribution->isAccepted() ? $distribution->lot->status->label() : null,
                'quantity' => $distribution->quantity,
                // The lot's stock is the distributor's only while it holds the lot.
                'remaining' => $distribution->isAccepted() ? $distribution->lot->quantity : $distribution->quantity,
                'production_date' => $distribution->lot->production_date->toDateString(),
                'expiration_date' => $distribution->lot->expiration_date?->toDateString(),
                'sent_at' => $distribution->created_at->toIso8601String(),
                'received_at' => $distribution->isAccepted() ? $distribution->reception_date?->toDateString() : null,
                'sender' => $distribution->sender->displayName(),
            ])->all(),
            'sales' => $sales->map(fn (TraceabilityEvent $sale) => [
                'lot' => $sale->lot->lot_number,
                'product' => $sale->lot->product->name,
                'unit' => $sale->lot->unit->symbol(),
                'quantity' => (float) ($sale->metadata['quantity'] ?? 0),
                'date' => $sale->occurred_at->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * What the distributor needs to know before accepting a lot.
     *
     * @return array<string, mixed>
     */
    public function forReception(Distribution $distribution): array
    {
        $lot = $distribution->lot;
        $insights = $this->insights->for($lot);

        return [
            'today' => today()->toDateString(),
            'lot' => [
                'number' => $lot->lot_number,
                'product' => $lot->product->name,
                'category' => $lot->product->category?->name,
                'unit' => $lot->unit->symbol(),
                'quantity' => $lot->quantity,
                'production_date' => $lot->production_date->toDateString(),
                'expiration_date' => $lot->expiration_date?->toDateString(),
            ],
            'distribution' => [
                'quantity' => $distribution->quantity,
                'sender' => $distribution->sender->displayName(),
                'sent_at' => $distribution->created_at->toDateString(),
            ],
            'certifications' => $insights->certifications->map(fn (Certification $certification) => [
                'name' => $certification->name,
                'type' => $certification->type->value,
                'status' => $certification->status->value,
                'valid' => $certification->isValid(),
                'expiration_date' => $certification->expiration_date?->toDateString(),
            ])->values()->all(),
            'warnings' => $insights->warnings->map(fn (array $warning) => [
                'severity' => $warning['severity'],
                'code' => $warning['code'],
                'message' => $warning['message'],
            ])->values()->all(),
            'chain_valid' => $insights->chain->valid,
            'under_investigation' => $insights->underInvestigation,
            'transparency_score' => $insights->trust->score,
            'transport' => $distribution->transport ? $this->context->forTransport($distribution->transport) : null,
        ];
    }

    /**
     * A submitted certificate with its proof, for the risk check.
     *
     * @return array<string, mixed>
     */
    public function forCertification(Certification $certification): array
    {
        $target = $certification->certifiable;

        return [
            'id' => $certification->id,
            'today' => today()->toDateString(),
            'name' => $certification->name,
            'type' => $certification->type->value,
            'issuer' => $certification->issuing_organization,
            'number' => $certification->certificate_number,
            'issue_date' => $certification->issue_date->toDateString(),
            'expiration_date' => $certification->expiration_date?->toDateString(),
            'status' => $certification->status->value,
            'owner' => [
                'name' => $certification->owner->displayName(),
                'organization' => $certification->owner->organization?->name ?? $certification->owner->displayName(),
                'verified' => (bool) $certification->owner->organization?->is_verified,
            ],
            'target' => [
                'kind' => $target instanceof Lot ? 'lot' : 'produit',
                'name' => $target instanceof Lot ? $target->product->name : $target?->name,
                'production_methods' => $this->productionMethods($target),
            ],
            // Same number on another certificate: a copied or invented number.
            'duplicates' => filled($certification->certificate_number)
                ? Certification::where('certificate_number', $certification->certificate_number)->whereKeyNot($certification->id)->count()
                : 0,
            'document' => $this->document($certification),
        ];
    }

    /**
     * Fingerprint of the data the AI saw. The proof is fingerprinted by its path, not its content.
     *
     * @param  array<string, mixed>  $context
     */
    public function version(array $context): string
    {
        if (isset($context['document']['base64'])) {
            $context['document']['base64'] = hash('sha256', $context['document']['base64']);
        }

        return $this->context->version($context);
    }

    /**
     * @return list<string>
     */
    private function productionMethods(Product|Lot|null $target): array
    {
        $lots = match (true) {
            $target instanceof Lot => collect([$target]),
            $target instanceof Product => $target->lots()->with('production')->get(),
            default => collect(),
        };

        return $lots->map(fn (Lot $lot) => $lot->production?->production_method?->label())
            ->filter()
            ->map(fn (string $label) => mb_strtolower($label))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function document(Certification $certification): array
    {
        $disk = Storage::disk(CertificationService::DISK);

        if (! $certification->document_path || ! $disk->exists($certification->document_path)) {
            return ['present' => false];
        }

        $isPdf = str_ends_with(strtolower($certification->document_path), '.pdf');

        return [
            'present' => true,
            'mime' => $isPdf ? 'application/pdf' : 'image/'.pathinfo($certification->document_path, PATHINFO_EXTENSION),
            // Only PDFs are read (text layer); images are reported as unreadable.
            'base64' => $isPdf ? base64_encode($disk->get($certification->document_path)) : null,
        ];
    }
}
