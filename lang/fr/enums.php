<?php

return [

    'product_status' => [
        'draft' => 'Brouillon',
        'published' => 'Publié',
        'archived' => 'Archivé',
    ],

    'lot_status' => [
        'created' => 'Créé',
        'in_transformation' => 'En transformation',
        'in_transit' => 'En transit',
        'distributed' => 'Distribué',
        'in_store' => 'En magasin',
        'sold_out' => 'Épuisé',
    ],

    'unit' => [
        'kg' => 'Kilogramme (kg)',
        't' => 'Tonne (t)',
        'L' => 'Litre (L)',
        'unit' => 'Pièce',
    ],

    'unit_symbol' => [
        'unit' => 'pièce(s)',
    ],

    'transport_type' => [
        'truck' => 'Camion',
        'van' => 'Camionnette',
        'train' => 'Train',
        'ship' => 'Bateau',
        'plane' => 'Avion',
    ],

    'transport_status' => [
        'in_transit' => 'En route',
        'delivered' => 'Livré',
        'cancelled' => 'Annulé',
    ],

    'transfer_status' => [
        'pending' => 'En attente de réception',
        'accepted' => 'Reçu',
        'rejected' => 'Refusé',
    ],

    'distribution_status' => [
        'pending' => 'En attente de réception',
        'received' => 'Reçu',
        'in_store' => 'En magasin',
        'rejected' => 'Refusé',
    ],

    'event_type' => [
        'PRODUCTION' => 'Production',
        'TRANSFORMATION' => 'Transformation',
        'TRANSPORT' => 'Transport',
        'DISTRIBUTION' => 'Distribution',
        'SALE' => 'Vente',
    ],

    'certification_type' => [
        'BIO' => 'Agriculture biologique',
        'LOCAL' => 'Produit local',
        'FAIR_TRADE' => 'Commerce équitable',
        'SUSTAINABLE_AGRICULTURE' => 'Agriculture durable',
        'OTHER' => 'Autre',
    ],

    'certification_status' => [
        'PENDING' => 'En attente de vérification',
        'VERIFIED' => 'Vérifiée',
        'EXPIRED' => 'Expirée',
        'REJECTED' => 'Refusée',
    ],

    'data_source' => [
        'MEASURED' => 'Mesuré',
        'PROVIDED' => 'Déclaré',
        'CALCULATED' => 'Calculé',
    ],

    'report_type' => [
        'SUSPICIOUS_INFORMATION' => 'Information suspecte',
        'MISLEADING_ENVIRONMENTAL_CLAIM' => 'Allégation environnementale trompeuse',
        'INVALID_CERTIFICATION' => 'Certification invalide',
    ],

    'report_status' => [
        'PENDING' => 'En attente',
        'UNDER_REVIEW' => 'En cours d\'examen',
        'RESOLVED' => 'Résolu',
        'REJECTED' => 'Rejeté',
    ],

    'production_method' => [
        'conventional' => 'Conventionnelle',
        'organic' => 'Biologique',
        'integrated' => 'Raisonnée',
        'permaculture' => 'Permaculture',
        'other' => 'Autre',
    ],
];
