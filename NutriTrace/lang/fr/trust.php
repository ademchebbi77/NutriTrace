<?php

return [
    'title' => 'Score de transparence',
    'why' => 'Pourquoi ce score ?',
    'out_of' => 'sur 100',
    'intro' => 'Ce score ne juge pas la qualité du produit : il mesure ce qui est réellement documenté et vérifiable dans son parcours.',
    'points' => ':points / :max',
    'penalties_title' => 'Pénalités',
    'no_penalty' => 'Aucune pénalité.',
    'not_available' => 'Non calculé',

    'levels' => [
        'high' => 'Transparence élevée',
        'medium' => 'Transparence partielle',
        'low' => 'Transparence faible',
    ],

    'components' => [
        'chain_completeness' => 'Parcours complet',
        'verified_actors' => 'Acteurs vérifiés',
        'certifications' => 'Certifications valides',
        'environmental_data' => 'Données environnementales chiffrées',
        'chain_integrity' => 'Intégrité du journal',
    ],

    'checks' => [
        'origin_known' => 'origine connue',
        'origin_geolocated' => 'origine géolocalisée',
        'transport_documented' => 'transport documenté',
        'distribution_recorded' => 'distribution enregistrée',
        'transformation_documented' => 'transformation décrite',
    ],

    'details' => [
        'chain_complete' => 'Origine, transport, transformation et distribution sont documentés.',
        'chain_missing' => 'Éléments manquants : :items.',
        'verified_actors' => ':verified acteur(s) vérifié(s) sur :total.',
        'no_certification' => 'Aucune certification vérifiée et en cours de validité.',
        'certification_without_proof' => ':count certification(s) valide(s), mais sans numéro ou document.',
        'certification_proven' => ':count certification(s) valide(s) avec numéro et document.',
        'environmental_data' => ':known indicateur(s) chiffré(s) sur :total.',
        'chain_intact' => 'Les :count événements du journal sont intacts.',
        'chain_broken' => 'Le journal a été modifié après son enregistrement.',
    ],

    'penalties' => [
        'expired_certification' => 'Certification expirée',
        'rejected_certification' => 'Certification refusée',
        'open_report' => 'Signalement ouvert',
        'chain_gap' => 'Incohérence dans le parcours',
    ],

    'local' => [
        'yes' => 'Local',
        'no' => 'Non local',
        'unknown' => 'Local : non déterminé',
        'explain_yes' => 'Produit à :km km de son lieu de vente (rayon retenu : :radius km).',
        'explain_no' => 'Produit à :km km de son lieu de vente, au-delà du rayon de :radius km.',
        'explain_unknown' => 'La distance entre le lieu de production et le lieu de vente n\'est pas encore connue.',
    ],
];
