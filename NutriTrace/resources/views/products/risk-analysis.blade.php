@extends('layouts.layout')

@section('title', 'Analyse de risques - ' . $product->name)

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-brain text-info"></i>
            Analyse de Risques & Traçabilité
        </h1>
        <div>
            <a href="{{ area_route('products.show', $product) }}" class="btn btn-sm btn-light shadow-sm">
                <i class="fas fa-arrow-left fa-sm"></i> Retour au produit</a>
            <a href="{{ area_route('products.risk-analysis', $product) }}" class="btn btn-sm btn-info shadow-sm">
                <i class="fas fa-sync fa-sm text-white-50"></i> Actualiser l'analyse</a>
        </div>
    </div>

    @if($analysis['error'])
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            Une erreur s'est produite lors de l'analyse : {{ $analysis['error'] }}
        </div>
    @endif

    <!-- Product Info Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Produit analysé</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h5 class="font-weight-bold">{{ $product->name }}</h5>
                    <p class="text-muted mb-2">
                        <i class="fas fa-tag"></i> {{ $analysis['data_summary']['category'] }} •
                        <i class="fas fa-map-marker-alt"></i> {{ $analysis['data_summary']['origin'] }} •
                        <i class="fas fa-building"></i> {{ $analysis['data_summary']['creator'] }}
                    </p>
                    <p class="mb-0">
                        <span class="badge badge-info">{{ $analysis['data_summary']['lots_count'] }} lot(s)</span>
                        <span class="badge badge-success">{{ $analysis['data_summary']['valid_certifications_count'] }}/{{ $analysis['data_summary']['certifications_count'] }} certification(s) valide(s)</span>
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <div class="display-4 mb-2">
                        @php
                            $riskBadgeClass = match($analysis['risk_level']) {
                                'high' => 'danger',
                                'medium' => 'warning',
                                'low' => 'info',
                                'very_low' => 'success',
                                default => 'secondary'
                            };
                            $riskLabel = match($analysis['risk_level']) {
                                'high' => 'Élevé',
                                'medium' => 'Moyen',
                                'low' => 'Faible',
                                'very_low' => 'Très faible',
                                default => 'Inconnu'
                            };
                        @endphp
                        <span class="badge badge-{{ $riskBadgeClass }} p-3">
                            <i class="fas fa-exclamation-circle"></i> {{ $riskLabel }}
                        </span>
                    </div>
                    <p class="text-muted small mb-0">Niveau de risque global</p>
                    <p class="text-muted small">Score : {{ $analysis['risk_score'] }}/100</p>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Explanation Card -->
    @if($analysis['ai_explanation'])
        <div class="card shadow mb-4 border-left-info">
            <div class="card-header py-3 bg-info text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-lightbulb"></i> Analyse IA - Évaluation Générale
                </h6>
            </div>
            <div class="card-body">
                <p class="mb-0">{{ $analysis['ai_explanation'] }}</p>
            </div>
        </div>
    @endif

    <!-- Risk Categories -->
    <div class="row">
        @foreach($analysis['categories'] as $categoryKey => $category)
            @php
                $categoryLabels = [
                    'traceability_completeness' => ['Complétude de la traçabilité', 'clipboard-list', 'primary'],
                    'data_consistency' => ['Cohérence des données', 'check-circle', 'success'],
                    'quality_and_storage' => ['Qualité et conservation', 'box', 'warning'],
                    'certification_verification' => ['Vérification des certifications', 'certificate', 'info']
                ];
                $label = $categoryLabels[$categoryKey] ?? [$categoryKey, 'info-circle', 'secondary'];
                $severityBadge = match($category['severity']) {
                    'high' => 'danger',
                    'medium' => 'warning',
                    'low' => 'info',
                    default => 'secondary'
                };
            @endphp
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100 border-left-{{ $label[2] }}">
                    <div class="card-header py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-{{ $label[2] }}">
                                <i class="fas fa-{{ $label[1] }}"></i> {{ $label[0] }}
                            </h6>
                            <span class="badge badge-{{ $severityBadge }}">
                                {{ ucfirst($category['severity']) }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(empty($category['issues']))
                            <p class="text-success mb-0">
                                <i class="fas fa-check-circle"></i> Aucun problème détecté dans cette catégorie.
                            </p>
                        @else
                            <ul class="mb-0 pl-3">
                                @foreach($category['issues'] as $issue)
                                    <li class="mb-2">{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mt-3">
                            <small class="text-muted">Score de risque : {{ $category['score'] }}</small>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Recommendations Card -->
    @if(!empty($analysis['recommendations']))
        <div class="card shadow mb-4 border-left-success">
            <div class="card-header py-3 bg-success text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-tasks"></i> Recommandations d'amélioration
                </h6>
            </div>
            <div class="card-body">
                <p class="font-weight-bold mb-3">Actions prioritaires pour améliorer la traçabilité :</p>
                <ol class="mb-0">
                    @foreach($analysis['recommendations'] as $recommendation)
                        <li class="mb-2">{{ $recommendation }}</li>
                    @endforeach
                </ol>
            </div>
        </div>
    @endif

    <!-- Analysis Metadata -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted small mb-0">
                        <i class="fas fa-clock"></i>
                        Analyse effectuée le : {{ \Carbon\Carbon::parse($analysis['analyzed_at'])->format('d/m/Y à H:i:s') }}
                    </p>
                </div>
                <div class="col-md-6 text-right">
                    <p class="text-muted small mb-0">
                        <i class="fas fa-info-circle"></i>
                        @if($analysis['ai_explanation'])
                            Analyse alimentée par l'IA Cohere
                        @else
                            Analyse basée sur des règles déterministes
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Important Notice -->
    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle"></i>
        <strong>Note importante :</strong> Cette analyse évalue la complétude et la cohérence des données de traçabilité.
        Elle ne constitue pas une évaluation de la sécurité alimentaire du produit. Les problèmes identifiés indiquent
        des lacunes dans la documentation, pas nécessairement des risques pour la santé. Pour toute question de sécurité
        alimentaire, consultez les autorités compétentes.
    </div>

@endsection
