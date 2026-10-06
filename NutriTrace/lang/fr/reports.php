<?php

return [
    'title' => 'Signalements',
    'button' => 'Signaler une information suspecte',
    'modal_title' => 'Signaler une information suspecte',
    'modal_intro' => 'Votre signalement est transmis à un administrateur, qui vérifiera l\'information et vous répondra.',
    'submit' => 'Envoyer le signalement',
    'submitted' => 'Merci : votre signalement a été transmis à un administrateur.',
    'login_to_report' => 'Connectez-vous pour signaler une information suspecte.',
    'under_investigation' => 'Un signalement concernant ce lot est en cours d\'examen par un administrateur.',

    'fields' => [
        'target' => 'Que souhaitez-vous signaler ?',
        'type' => 'Type de problème',
        'description' => 'Décrivez ce qui vous semble suspect',
        'status' => 'Statut',
        'date' => 'Date',
        'author' => 'Auteur',
        'concerns' => 'Concerne',
        'response' => 'Réponse de l\'administrateur',
        'no_response' => 'Pas encore de réponse.',
    ],

    'attributes' => [
        'target' => 'élément signalé',
        'type' => 'type de problème',
        'description' => 'description',
        'status' => 'statut',
        'admin_response' => 'réponse',
    ],

    'target_options' => [
        'lot' => 'Ce lot (:name)',
        'product' => 'Le produit (:name)',
        'impact' => 'Les données environnementales',
        'certification' => 'La certification « :name »',
    ],

    'targets' => [
        'lot' => 'Lot :name',
        'product' => 'Produit :name',
        'certification' => 'Certification :name',
        'impact' => 'Données environnementales du lot :name',
        'deleted' => 'Élément supprimé',
    ],

    'validation' => [
        'target' => 'L\'élément signalé est introuvable.',
    ],

    'mine' => [
        'title' => 'Mes signalements',
        'empty' => 'Vous n\'avez envoyé aucun signalement.',
    ],

    'admin' => [
        'title' => 'Modération des signalements',
        'list' => 'Tous les signalements',
        'empty' => 'Aucun signalement.',
        'show_title' => 'Signalement n° :id',
        'report_card' => 'Signalement',
        'decision_card' => 'Décision',
        'decision' => 'Nouveau statut',
        'response' => 'Réponse à l\'auteur',
        'response_help' => 'Obligatoire pour résoudre ou rejeter. « En cours d\'examen » affiche un bandeau sur la page publique du lot.',
        'save' => 'Enregistrer la décision',
        'updated' => 'Le signalement a été mis à jour.',
        'open_lot' => 'Voir le lot concerné',
        'reviewed_by' => 'Traité par :name le :date',
    ],
];
