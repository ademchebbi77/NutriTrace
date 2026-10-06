<?php

return [
    'title' => 'Données environnementales',
    'list' => 'Empreinte de mes lots',
    'empty' => 'Aucun lot en votre possession.',
    'edit_title' => 'Empreinte du lot :number',
    'declare' => 'Déclarer',
    'updated' => 'Les données environnementales ont été enregistrées et l\'empreinte recalculée.',
    'card' => 'Empreinte environnementale',
    'not_available' => 'Non évaluée',
    'per_kg' => 'par kg',
    'calculated_at' => 'Calculée le :date',

    'intro' => 'L\'empreinte est calculée automatiquement à partir des productions, transformations et transports enregistrés. Vous pouvez la compléter avec vos propres chiffres : indiquez s\'ils sont mesurés (compteur, facture, pesée) ou simplement déclarés.',
    'declared_card' => 'Vos chiffres pour ce lot',
    'declared_help' => 'Laissez un champ vide pour conserver la valeur calculée. Une valeur saisie remplace le calcul pour cet indicateur.',
    'result_card' => 'Empreinte actuelle',
    'stages_card' => 'Répartition du CO₂ par étape',
    'no_stage_data' => 'Pas encore assez de données pour répartir le CO₂ par étape.',

    'indicators' => [
        'co2_kg' => 'CO₂ total',
        'water_l' => 'Eau',
        'energy_kwh' => 'Énergie',
        'waste_kg' => 'Déchets',
        'transport_co2_kg' => 'CO₂ du transport',
        'packaging_co2_kg' => 'CO₂ de l\'emballage',
        'food_miles_km' => 'Distance parcourue',
    ],

    'units' => [
        'co2_kg' => 'kg CO₂e',
        'water_l' => 'L',
        'energy_kwh' => 'kWh',
        'waste_kg' => 'kg',
        'transport_co2_kg' => 'kg CO₂e',
        'packaging_co2_kg' => 'kg CO₂e',
        'food_miles_km' => 'km',
    ],

    'stages' => [
        'production' => 'Production',
        'transformation' => 'Transformation',
        'transport' => 'Transport',
        'packaging' => 'Emballage',
    ],

    'fields' => [
        'indicator' => 'Indicateur',
        'value' => 'Valeur',
        'source' => 'Origine du chiffre',
        'choose_source' => 'Choisir',
        'grade' => 'Note',
        'score' => 'Score',
        'current' => 'Valeur actuelle',
    ],

    'attributes' => [
        'value' => 'valeur',
        'source' => 'origine du chiffre',
    ],

    'source_help' => [
        'MEASURED' => 'Mesuré : relevé par un instrument ou un document (compteur, facture, pesée).',
        'PROVIDED' => 'Déclaré : fourni par un acteur de la chaîne, sans mesure vérifiable.',
        'CALCULATED' => 'Calculé : estimé par NutriTrace à partir des données enregistrées et de facteurs d\'émission simplifiés.',
    ],
];
