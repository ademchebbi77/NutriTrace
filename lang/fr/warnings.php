<?php

return [
    'title' => 'Points de vigilance',
    'none' => 'Aucune incohérence détectée entre ce que ce lot annonce et ce qui est prouvé.',
    'related' => 'Concerne :',

    'severity' => [
        'high' => 'Important',
        'medium' => 'À surveiller',
        'low' => 'Information',
    ],

    'messages' => [
        'bio_unverified' => 'Mention « bio » sans certificat biologique vérifié.',
        'certificate_expired' => 'Certificat expiré depuis le :date : il n\'est plus pris en compte.',
        'certificate_rejected' => 'Certificat refusé lors de la vérification.',
        'certificate_pending' => 'Certificat en cours de vérification : il n\'est pas encore pris en compte.',
        'certificate_without_proof' => 'Certificat vérifié mais sans numéro ou document consultable.',
        'local_claim_far' => 'Mention « local » alors que le produit a parcouru :km km.',
        'environmental_data_missing' => 'Aucune donnée environnementale chiffrée : l\'empreinte ne peut pas être évaluée.',
        'environmental_data_partial' => 'Données environnementales incomplètes (eau ou énergie non déclarées).',
        'chain_broken' => 'Le journal de traçabilité a été modifié après coup : son intégrité n\'est plus garantie.',
        'unverified_actors' => 'Acteur(s) dont l\'organisation n\'a pas été vérifiée.',
        'under_investigation' => 'Un signalement concernant ce lot est en cours d\'examen.',
    ],

    'consistency' => [
        'transformation_before_source' => 'Transformation datée avant la production de sa matière première.',
        'transport_before_production' => 'Transport daté avant la production du lot.',
        'arrival_before_departure' => 'Arrivée du transport datée avant son départ.',
        'transport_without_distance' => 'Transport sans distance renseignée.',
        'reception_before_shipping' => 'Réception datée avant l\'expédition.',
        'distributed_after_expiration' => 'Lot expédié après sa date limite de consommation.',
        'origin_not_geolocated' => 'Lieu de production non géolocalisé.',
    ],
];
