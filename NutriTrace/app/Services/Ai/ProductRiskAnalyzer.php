<?php

namespace App\Services\Ai;

use App\Enums\CertificationStatus;
use App\Enums\LotStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Analyzes product and traceability data for risk assessment.
 * Performs deterministic checks and uses Cohere AI for interpretation and recommendations.
 */
class ProductRiskAnalyzer
{
    public function __construct(
        private readonly CohereClient $cohere
    ) {}

    /**
     * Analyze a product for traceability and data quality risks.
     *
     * @return array{
     *     success: bool,
     *     risk_level: string,
     *     risk_score: int,
     *     categories: array<string, array{severity: string, issues: array<string>, score: int}>,
     *     recommendations: array<string>,
     *     data_summary: array<string, mixed>,
     *     ai_explanation: ?string,
     *     analyzed_at: string,
     *     error: ?string
     * }
     */
    public function analyze(Product $product): array
    {
        // Load all necessary relationships
        $product->load([
            'category',
            'creator.organization',
            'lots.production.producer.organization',
            'lots.transformation',
            'lots.transports',
            'lots.distributions',
            'lots.certifications',
            'lots.currentHolder.organization',
            'certifications',
        ]);

        // Perform deterministic checks
        $checks = $this->performDeterministicChecks($product);

        // Calculate risk score
        $riskScore = $this->calculateRiskScore($checks);
        $riskLevel = $this->determineRiskLevel($riskScore);

        // Prepare data summary
        $dataSummary = $this->prepareDataSummary($product, $checks);

        // Get AI-powered recommendations if Cohere is configured
        $aiExplanation = null;
        $recommendations = [];

        if ($this->cohere->isConfigured()) {
            $aiResponse = $this->getAiRecommendations($product, $checks, $dataSummary);
            $aiExplanation = $aiResponse['explanation'] ?? null;
            $recommendations = $aiResponse['recommendations'] ?? [];
        } else {
            // Fallback to basic recommendations if AI is not available
            $recommendations = $this->generateBasicRecommendations($checks);
        }

        return [
            'success' => true,
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'categories' => $checks,
            'recommendations' => $recommendations,
            'data_summary' => $dataSummary,
            'ai_explanation' => $aiExplanation,
            'analyzed_at' => now()->toIso8601String(),
            'error' => null,
        ];
    }

    /**
     * Perform deterministic checks on product data.
     *
     * @return array<string, array{severity: string, issues: array<string>, score: int}>
     */
    private function performDeterministicChecks(Product $product): array
    {
        return [
            'traceability_completeness' => $this->checkTraceabilityCompleteness($product),
            'data_consistency' => $this->checkDataConsistency($product),
            'quality_and_storage' => $this->checkQualityAndStorage($product),
            'certification_verification' => $this->checkCertificationVerification($product),
        ];
    }

    /**
     * Check traceability completeness.
     *
     * @return array{severity: string, issues: array<string>, score: int}
     */
    private function checkTraceabilityCompleteness(Product $product): array
    {
        $issues = [];
        $score = 0;

        // Check basic product information
        if (blank($product->origin)) {
            $issues[] = 'Origine du produit non renseignée';
            $score += 15;
        }

        if (blank($product->description)) {
            $issues[] = 'Description du produit manquante';
            $score += 5;
        }

        if ($product->status === ProductStatus::DRAFT) {
            $issues[] = 'Produit en statut brouillon, non publié';
            $score += 10;
        }

        // Check lots
        $lotsCount = $product->lots->count();
        if ($lotsCount === 0) {
            $issues[] = 'Aucun lot associé au produit';
            $score += 20;
        } else {
            // Check production records
            $lotsWithoutProduction = $product->lots->filter(fn ($lot) => ! $lot->production)->count();
            if ($lotsWithoutProduction > 0) {
                $issues[] = "$lotsWithoutProduction lot(s) sans enregistrement de production";
                $score += 15;
            }

            // Check transport records
            $lotsWithTransport = $product->lots->filter(fn ($lot) => $lot->transports->isNotEmpty())->count();
            if ($lotsWithTransport === 0 && $lotsCount > 0) {
                $issues[] = 'Aucun enregistrement de transport pour les lots';
                $score += 10;
            }

            // Check distribution records
            $distributedLots = $product->lots->filter(fn ($lot) => in_array($lot->status, [
                LotStatus::DISTRIBUTED,
                LotStatus::IN_STORE,
                LotStatus::SOLD_OUT,
            ]))->count();

            $lotsWithDistribution = $product->lots->filter(fn ($lot) => $lot->distributions->isNotEmpty())->count();

            if ($distributedLots > 0 && $lotsWithDistribution === 0) {
                $issues[] = 'Lots marqués comme distribués mais sans enregistrement de distribution';
                $score += 15;
            }
        }

        $severity = $score >= 40 ? 'high' : ($score >= 20 ? 'medium' : 'low');

        return compact('severity', 'issues', 'score');
    }

    /**
     * Check data consistency.
     *
     * @return array{severity: string, issues: array<string>, score: int}
     */
    private function checkDataConsistency(Product $product): array
    {
        $issues = [];
        $score = 0;

        foreach ($product->lots as $lot) {
            // Check date consistency
            if ($lot->expiration_date && $lot->production_date) {
                if ($lot->expiration_date->lt($lot->production_date)) {
                    $issues[] = "Lot {$lot->lot_number}: date de péremption antérieure à la date de production";
                    $score += 20;
                }
            }

            // Check quantity consistency
            if ($lot->quantity < 0) {
                $issues[] = "Lot {$lot->lot_number}: quantité négative ({$lot->formattedQuantity()})";
                $score += 15;
            }

            if ($lot->quantity > $lot->initial_quantity) {
                $issues[] = "Lot {$lot->lot_number}: quantité actuelle supérieure à la quantité initiale";
                $score += 10;
            }

            // Check transport consistency
            foreach ($lot->transports as $transport) {
                if ($transport->arrival_date && $transport->departure_date) {
                    if ($transport->arrival_date->lt($transport->departure_date)) {
                        $issues[] = "Lot {$lot->lot_number}: transport avec date d'arrivée antérieure au départ";
                        $score += 20;
                    }
                }

                if ($transport->departure_date && $lot->production_date) {
                    if ($transport->departure_date->lt($lot->production_date)) {
                        $issues[] = "Lot {$lot->lot_number}: transport avant la date de production";
                        $score += 20;
                    }
                }
            }

            // Check transformation consistency
            if ($lot->transformation) {
                $transformation = $lot->transformation;
                if ($transformation->transformation_date && $lot->production_date) {
                    if ($transformation->transformation_date->lt($lot->production_date)) {
                        $issues[] = "Lot {$lot->lot_number}: transformation avant la production des intrants";
                        $score += 20;
                    }
                }
            }
        }

        $severity = $score >= 40 ? 'high' : ($score >= 20 ? 'medium' : 'low');

        return compact('severity', 'issues', 'score');
    }

    /**
     * Check quality and storage concerns.
     *
     * @return array{severity: string, issues: array<string>, score: int}
     */
    private function checkQualityAndStorage(Product $product): array
    {
        $issues = [];
        $score = 0;

        foreach ($product->lots as $lot) {
            // Check expiration
            if ($lot->isExpired()) {
                $issues[] = "Lot {$lot->lot_number}: périmé depuis le {$lot->expiration_date->format('d/m/Y')}";
                $score += 25;
            } elseif ($lot->expiration_date && $lot->expiration_date->diffInDays(today()) <= 7) {
                $issues[] = "Lot {$lot->lot_number}: expire dans moins de 7 jours";
                $score += 10;
            }

            // Check if lot has no expiration date when it might be expected
            if (! $lot->expiration_date && $product->category?->name) {
                $perishableCategories = ['Produits laitiers', 'Viandes', 'Poissons', 'Fruits', 'Légumes'];
                if (in_array($product->category->name, $perishableCategories)) {
                    $issues[] = "Lot {$lot->lot_number}: date de péremption manquante pour un produit périssable";
                    $score += 10;
                }
            }

            // Check production resources if available
            if ($lot->production && $lot->production->resources_used) {
                $resources = $lot->production->resources_used;

                // Check for unusually high pesticide use
                if (isset($resources['pesticide_kg']) && $resources['pesticide_kg'] > 100) {
                    $issues[] = "Lot {$lot->lot_number}: utilisation élevée de pesticides ({$resources['pesticide_kg']} kg)";
                    $score += 5;
                }
            }
        }

        $severity = $score >= 40 ? 'high' : ($score >= 20 ? 'medium' : 'low');

        return compact('severity', 'issues', 'score');
    }

    /**
     * Check certification verification.
     *
     * @return array{severity: string, issues: array<string>, score: int}
     */
    private function checkCertificationVerification(Product $product): array
    {
        $issues = [];
        $score = 0;

        // Check product-level certifications
        $productCertifications = $product->certifications;
        $lotCertifications = $product->lots->flatMap->certifications;
        $allCertifications = $productCertifications->concat($lotCertifications);

        if ($allCertifications->isEmpty()) {
            $issues[] = 'Aucune certification associée au produit ou à ses lots';
            $score += 10;
        } else {
            // Check for expired certifications
            $expiredCount = $allCertifications->filter(fn ($cert) => $cert->isPastExpiration())->count();
            if ($expiredCount > 0) {
                $issues[] = "$expiredCount certification(s) expirée(s)";
                $score += 15 * min($expiredCount, 3); // Cap the score increase
            }

            // Check for pending certifications
            $pendingCount = $allCertifications->filter(fn ($cert) => $cert->status === CertificationStatus::PENDING)->count();
            if ($pendingCount > 0) {
                $issues[] = "$pendingCount certification(s) en attente de vérification";
                $score += 10;
            }

            // Check for rejected certifications
            $rejectedCount = $allCertifications->filter(fn ($cert) => $cert->status === CertificationStatus::REJECTED)->count();
            if ($rejectedCount > 0) {
                $issues[] = "$rejectedCount certification(s) rejetée(s)";
                $score += 20 * min($rejectedCount, 2);
            }

            // Check for certifications without proof
            $withoutProofCount = $allCertifications->filter(fn ($cert) => ! $cert->hasProof())->count();
            if ($withoutProofCount > 0) {
                $issues[] = "$withoutProofCount certification(s) sans preuve ou numéro de certificat";
                $score += 5 * min($withoutProofCount, 3);
            }
        }

        $severity = $score >= 40 ? 'high' : ($score >= 20 ? 'medium' : 'low');

        return compact('severity', 'issues', 'score');
    }

    /**
     * Calculate overall risk score from checks.
     */
    private function calculateRiskScore(array $checks): int
    {
        return array_sum(array_column($checks, 'score'));
    }

    /**
     * Determine risk level from score.
     */
    private function determineRiskLevel(int $score): string
    {
        if ($score >= 60) {
            return 'high';
        }

        if ($score >= 30) {
            return 'medium';
        }

        if ($score >= 10) {
            return 'low';
        }

        return 'very_low';
    }

    /**
     * Prepare data summary for display and AI analysis.
     *
     * @return array<string, mixed>
     */
    private function prepareDataSummary(Product $product, array $checks): array
    {
        $totalIssues = array_sum(array_map(fn ($cat) => count($cat['issues']), $checks));

        return [
            'product_name' => $product->name,
            'product_status' => $product->status->label(),
            'origin' => $product->origin ?? 'Non renseignée',
            'category' => $product->category?->name ?? 'Non catégorisé',
            'creator' => $product->creator->organization?->name ?? $product->creator->name,
            'lots_count' => $product->lots->count(),
            'certifications_count' => $product->certifications->count() + $product->lots->sum(fn ($lot) => $lot->certifications->count()),
            'valid_certifications_count' => $product->certifications->filter->isValid()->count() +
                $product->lots->sum(fn ($lot) => $lot->certifications->filter->isValid()->count()),
            'total_issues_found' => $totalIssues,
            'has_description' => filled($product->description),
            'has_barcode' => filled($product->barcode),
        ];
    }

    /**
     * Get AI-powered recommendations from Cohere.
     *
     * @return array{explanation: ?string, recommendations: array<string>}
     */
    private function getAiRecommendations(Product $product, array $checks, array $dataSummary): array
    {
        $prompt = $this->buildAnalysisPrompt($product, $checks, $dataSummary);

        $messages = [
            [
                'role' => 'system',
                'content' => 'Vous êtes un expert en traçabilité alimentaire et en analyse de risques. Votre rôle est d\'analyser les données de traçabilité d\'un produit alimentaire et de fournir des recommandations concrètes et exploitables pour améliorer la qualité et la complétude des informations. Soyez précis, factuel et constructif. Ne faites pas de suppositions sur des informations qui ne sont pas fournies.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        $response = $this->cohere->chat($messages, [
            'temperature' => 0.3,
            'max_tokens' => 1500,
        ]);

        if (! $response) {
            Log::warning('Failed to get AI recommendations for product', ['product_id' => $product->id]);

            return ['explanation' => null, 'recommendations' => $this->generateBasicRecommendations($checks)];
        }

        // Parse the AI response
        return $this->parseAiResponse($response['text'], $checks);
    }

    /**
     * Build the analysis prompt for Cohere.
     */
    private function buildAnalysisPrompt(Product $product, array $checks, array $dataSummary): string
    {
        $issuesList = [];
        foreach ($checks as $category => $data) {
            if (! empty($data['issues'])) {
                $categoryName = $this->getCategoryLabel($category);
                $issuesList[] = "**{$categoryName}** (Sévérité: {$data['severity']}):";
                foreach ($data['issues'] as $issue) {
                    $issuesList[] = "  - {$issue}";
                }
            }
        }

        $issuesText = implode("\n", $issuesList);

        return <<<PROMPT
Analysez les données de traçabilité suivantes pour le produit "{$product->name}" et fournissez des recommandations concrètes.

**Résumé du produit:**
- Nom: {$dataSummary['product_name']}
- Catégorie: {$dataSummary['category']}
- Origine: {$dataSummary['origin']}
- Statut: {$dataSummary['product_status']}
- Producteur: {$dataSummary['creator']}
- Nombre de lots: {$dataSummary['lots_count']}
- Certifications valides: {$dataSummary['valid_certifications_count']} sur {$dataSummary['certifications_count']}

**Problèmes identifiés:**
{$issuesText}

**Consignes:**
1. Fournissez une brève explication (2-3 phrases) de l'état général de la traçabilité du produit
2. Listez 3 à 5 recommandations prioritaires et concrètes pour améliorer la situation
3. Basez-vous uniquement sur les problèmes identifiés ci-dessus
4. Ne suggérez pas de modifications automatiques des données
5. Orientez les recommandations vers des actions que l'utilisateur peut entreprendre

Répondez au format suivant:

EXPLICATION:
[Votre explication ici]

RECOMMANDATIONS:
1. [Première recommandation]
2. [Deuxième recommandation]
3. [Troisième recommandation]
...
PROMPT;
    }

    /**
     * Parse AI response to extract explanation and recommendations.
     *
     * @return array{explanation: string, recommendations: array<string>}
     */
    private function parseAiResponse(string $responseText, array $checks): array
    {
        $explanation = '';
        $recommendations = [];

        // Try to extract structured sections
        if (preg_match('/EXPLICATION:\s*\n(.+?)\n\nRECOMMANDATIONS:/s', $responseText, $matches)) {
            $explanation = trim($matches[1]);
        } elseif (preg_match('/EXPLICATION:\s*\n(.+)/s', $responseText, $matches)) {
            $explanation = trim($matches[1]);
        }

        if (preg_match('/RECOMMANDATIONS:\s*\n(.+)/s', $responseText, $matches)) {
            $recText = $matches[1];
            // Extract numbered or bulleted list items
            preg_match_all('/^[\d\-\*•]\s*[\.:\)]\s*(.+)$/m', $recText, $recMatches);
            if (! empty($recMatches[1])) {
                $recommendations = array_map('trim', $recMatches[1]);
            }
        }

        // Fallback: if parsing failed, use basic recommendations
        if (empty($recommendations)) {
            $recommendations = $this->generateBasicRecommendations($checks);
        }

        // If no explanation was parsed, create a basic one
        if (empty($explanation)) {
            $explanation = "L'analyse a identifié plusieurs problèmes de traçabilité nécessitant votre attention. Consultez les recommandations ci-dessous pour améliorer la qualité des données.";
        }

        return compact('explanation', 'recommendations');
    }

    /**
     * Generate basic recommendations when AI is not available.
     *
     * @return array<string>
     */
    private function generateBasicRecommendations(array $checks): array
    {
        $recommendations = [];

        // Traceability completeness
        if ($checks['traceability_completeness']['score'] > 0) {
            $recommendations[] = 'Compléter les informations de base du produit (origine, description)';
            if (str_contains(implode(' ', $checks['traceability_completeness']['issues']), 'production')) {
                $recommendations[] = 'Enregistrer les données de production pour tous les lots';
            }
            if (str_contains(implode(' ', $checks['traceability_completeness']['issues']), 'transport')) {
                $recommendations[] = 'Documenter les transports effectués pour assurer la traçabilité complète';
            }
        }

        // Data consistency
        if ($checks['data_consistency']['score'] > 0) {
            $recommendations[] = 'Vérifier et corriger les incohérences de dates dans les enregistrements';
            $recommendations[] = 'Contrôler les quantités et corriger les anomalies détectées';
        }

        // Quality and storage
        if ($checks['quality_and_storage']['score'] > 0) {
            if (str_contains(implode(' ', $checks['quality_and_storage']['issues']), 'périmé')) {
                $recommendations[] = 'Retirer les lots périmés de la circulation';
            }
            if (str_contains(implode(' ', $checks['quality_and_storage']['issues']), 'péremption manquante')) {
                $recommendations[] = 'Ajouter les dates de péremption pour les produits périssables';
            }
        }

        // Certification verification
        if ($checks['certification_verification']['score'] > 0) {
            $recommendations[] = 'Renouveler les certifications expirées ou à expiration proche';
            if (str_contains(implode(' ', $checks['certification_verification']['issues']), 'preuve')) {
                $recommendations[] = 'Joindre les documents justificatifs pour les certifications';
            }
        }

        // Limit to 5 most relevant recommendations
        return array_slice($recommendations, 0, 5);
    }

    /**
     * Get French label for category.
     */
    private function getCategoryLabel(string $category): string
    {
        return match ($category) {
            'traceability_completeness' => 'Complétude de la traçabilité',
            'data_consistency' => 'Cohérence des données',
            'quality_and_storage' => 'Qualité et conservation',
            'certification_verification' => 'Vérification des certifications',
            default => $category,
        };
    }
}
