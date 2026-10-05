<?php

return [
    'title' => 'Transferts',
    'sent_list' => 'Lots envoyés',
    'empty' => 'Vous n\'avez encore envoyé aucun lot.',
    'ready_title' => 'Lots prêts à être envoyés',
    'ready_empty' => 'Aucun lot disponible à l\'envoi pour le moment.',
    'send' => 'Envoyer',
    'send_title' => 'Envoyer le lot :number',
    'send_button' => 'Envoyer le lot',
    'how_it_works' => 'Le lot entier part en transit. Vous en restez le détenteur jusqu\'à ce que le destinataire confirme la réception ; s\'il le refuse, le lot vous revient.',

    'sections' => [
        'lot' => 'Lot à envoyer',
        'recipient' => 'Destinataire',
        'transport' => 'Transport',
    ],

    'fields' => [
        'lot' => 'Lot',
        'recipient' => 'Destinataire',
        'choose_recipient' => 'Choisir un transformateur ou un distributeur',
        'sender' => 'Expéditeur',
        'quantity' => 'Quantité',
        'transport_type' => 'Mode de transport',
        'departure_date' => 'Date et heure de départ',
        'distance_km' => 'Distance (km)',
        'distance_help' => 'Laissez vide pour un calcul automatique à vol d\'oiseau à partir des coordonnées GPS. Saisissez la distance réelle par la route si vous la connaissez.',
        'note' => 'Message au destinataire (facultatif)',
        'sent_at' => 'Envoyé le',
        'status' => 'Statut',
        'origin' => 'Départ',
        'destination' => 'Arrivée',
        'arrival_date' => 'Arrivée le',
        'reason' => 'Motif du refus :',
        'co2' => 'CO₂ du transport (calculé)',
    ],

    'attributes' => [
        'recipient_id' => 'destinataire',
        'transport_type' => 'mode de transport',
        'departure_date' => 'date de départ',
        'distance_km' => 'distance',
        'note' => 'message',
        'rejection_reason' => 'motif du refus',
    ],

    'sent' => 'Le lot :lot est en route vers :recipient.',
    'received' => 'Réception du lot :lot confirmée : vous en êtes maintenant le détenteur.',
    'refused' => 'Le lot :lot a été refusé et retourne à son expéditeur.',
    'transport_updated' => 'Le transport a été mis à jour.',

    'receptions' => [
        'title' => 'Réceptions',
        'pending' => 'Lots en attente de réception',
        'pending_empty' => 'Aucun lot en attente de réception.',
        'history' => 'Historique des réceptions',
        'history_empty' => 'Aucune réception enregistrée.',
        'confirm' => 'Confirmer la réception',
        'refuse' => 'Refuser',
        'refuse_title' => 'Refuser le lot',
        'refuse_reason' => 'Motif du refus (communiqué à l\'expéditeur)',
        'store_label' => 'Magasin ou zone de vente (facultatif)',
    ],

    'transports' => [
        'title' => 'Transports',
        'list' => 'Liste des transports',
        'empty' => 'Aucun transport pour le moment.',
        'show_title' => 'Transport du lot :number',
        'route' => 'Trajet',
        'correct' => 'Corriger le transport',
        'correct_help' => 'Possible tant que le lot est en route. La correction recalcule l\'empreinte du lot.',
        'no_distance' => 'Non renseignée',
        'direction_out' => 'Envoi',
        'direction_in' => 'Réception',
    ],
];
