<?php

// Descriptions written into the traceability journal. They are part of each event's hash:
// changing a sentence here only affects future events.
return [
    'production' => [
        'created' => 'Production de :quantity de :product (méthode :method).',
        'corrected' => 'Correction de la déclaration de production : :quantity de :product (méthode :method).',
    ],
    'transformation' => [
        'output' => 'Transformation : obtention de :quantity de :product.',
        'input' => ':quantity utilisés pour produire :product (lot :lot).',
    ],
    'transport' => [
        'departure' => 'Départ vers :destination en :mode (:distance km).',
        'arrival' => 'Arrivée à :destination.',
        'corrected' => 'Correction du transport : :mode, :distance km.',
        'cancelled' => 'Livraison refusée par :destination : retour à l\'expéditeur.',
    ],
    'distribution' => [
        'shipped' => 'Expédition de :quantity au distributeur :distributor.',
        'received' => 'Réception confirmée par :distributor (:destination).',
        'in_store' => 'Mise en rayon : :destination.',
        'rejected' => 'Lot refusé par le distributeur :distributor.',
    ],
    'sale' => [
        'sold' => 'Vente de :quantity (:destination).',
        'sold_out' => 'Vente de :quantity (:destination) : lot épuisé.',
    ],
];
