<?php

return [
    'title' => 'Distributions',
    'list' => 'Liste des distributions',
    'empty' => 'Aucune distribution pour le moment.',
    'show_title' => 'Distribution du lot :number',
    'information' => 'Informations',
    'in_store' => 'Le lot :lot est maintenant en rayon.',
    'mark_in_store' => 'Mettre en rayon',
    'to_receive' => 'Ce lot attend votre confirmation de réception.',
    'go_to_receptions' => 'Voir les réceptions',

    'fields' => [
        'lot' => 'Lot',
        'sender' => 'Expéditeur',
        'distributor' => 'Distributeur',
        'destination' => 'Magasin ou zone de vente',
        'quantity' => 'Quantité reçue',
        'remaining' => 'Stock restant',
        'distribution_date' => 'Expédié le',
        'reception_date' => 'Reçu le',
        'status' => 'Statut',
        'reason' => 'Motif du refus :',
    ],

    'attributes' => [
        'destination' => 'magasin ou zone de vente',
        'quantity' => 'quantité vendue',
    ],

    'sales' => [
        'title' => 'Ventes',
        'on_shelf' => 'Lots en rayon',
        'on_shelf_empty' => 'Aucun lot en rayon. Mettez un lot reçu en rayon pour enregistrer ses ventes.',
        'history' => 'Dernières ventes',
        'history_empty' => 'Aucune vente enregistrée.',
        'quantity' => 'Quantité vendue',
        'record' => 'Enregistrer la vente',
        'recorded' => 'La vente du lot :lot a été enregistrée.',
        'date' => 'Date',
        'validation_quantity' => 'La quantité vendue doit être positive et ne peut pas dépasser le stock (:max).',
    ],

    'labels' => [
        'title' => 'Étiquettes QR',
        'list' => 'Lots en votre possession',
        'empty' => 'Aucun lot en votre possession.',
        'print' => 'Imprimer l\'étiquette',
        'label_title' => 'Étiquette du lot :number',
        'scan' => 'Scannez pour connaître l\'origine, le parcours et l\'empreinte de ce produit.',
        'produced' => 'Produit le',
        'best_before' => 'À consommer avant le',
        'open_public' => 'Ouvrir la page publique',
    ],
];
