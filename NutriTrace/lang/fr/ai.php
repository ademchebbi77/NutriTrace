<?php

return [
    'assistant' => [
        'title' => 'Assistant IA de traçabilité',
        'subtitle' => 'Posez une question sur le parcours de ce lot : l\'assistant répond uniquement à partir des données enregistrées.',
        'question' => 'question',
        'placeholder' => 'Ex. : Quelle distance ce lot a-t-il parcourue ?',
        'ask' => 'Demander',
        'thinking' => 'L\'assistant réfléchit…',
        'suggestions' => 'Questions fréquentes',
        'unavailable' => 'L\'assistant est momentanément indisponible (service d\'IA local arrêté). Réessayez dans un instant.',
        'offline' => 'Le service d\'IA local est arrêté : seules les questions déjà posées sur ce lot obtiennent une réponse. Lancez ai/start.bat pour le démarrer.',
        'disclaimer' => 'Réponses générées par un modèle d\'IA local (Qwen2.5 affiné sur NutriTrace), sans service externe. Vérifiez les informations importantes dans le parcours ci-dessus.',
        'error' => 'Une erreur est survenue. Réessayez.',
        'you' => 'Vous',
        'bot' => 'Assistant',
        'examples' => [
            'Quelle distance ce lot a-t-il parcourue ?',
            'Quels modes de transport ont été utilisés ?',
            'Ce produit est-il vraiment local ?',
            'D\'où vient ce produit ?',
            'Quelle est son empreinte carbone ?',
            'La traçabilité de ce lot est-elle fiable ?',
        ],
    ],

    'analysis' => [
        'title' => 'Analyse IA du transport',
        'help' => 'Un modèle d\'apprentissage automatique entraîné sur les transports NutriTrace vérifie la cohérence de la distance, du mode, de la vitesse et des dates ; le modèle de langue local explique le résultat.',
        'run' => 'Analyser ce transport',
        'rerun' => 'Analyser à nouveau',
        'done' => 'Analyse IA terminée.',
        'unavailable' => 'Le service d\'IA local ne répond pas : analyse impossible pour le moment.',
        'normal' => 'Aucune anomalie détectée',
        'anomaly' => 'Anomalie détectée',
        'confidence' => 'Confiance du modèle : :value %',
        'reasons' => 'Éléments relevés',
        'explanation' => 'Explication',
        'analyzed_at' => 'Analysé le :date',
        'stale' => 'Le transport a changé depuis la dernière analyse.',
    ],

    // Labels predicted by the anomaly model (ai/nutritrace_ai/features.py).
    'labels' => [
        'normal' => 'Normal',
        'distance_inflated' => 'Distance anormalement longue',
        'distance_too_short' => 'Distance plus courte que la ligne droite',
        'speed_implausible' => 'Vitesse impossible pour ce mode',
        'mode_mismatch' => 'Mode de transport incohérent',
        'dates_inconsistent' => 'Dates incohérentes',
        'missing_distance' => 'Distance manquante',
    ],
];
