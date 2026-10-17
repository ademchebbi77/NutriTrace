"""Reference explanations of an anomaly verdict.

They are the training targets of the explanation task, and the text shown when the
service runs without its LLM (NUTRITRACE_NO_LLM=1).
"""

import random
from typing import Any

from .features import reasons

ADVICE = {
    "normal": [
        "Aucune correction n'est nécessaire.",
        "Le transport peut être conservé tel quel.",
        "Rien à corriger sur ce trajet.",
    ],
    "distance_inflated": [
        "Vérifiez la distance saisie : une erreur de frappe ou un trajet compté plusieurs fois gonfle l'empreinte carbone du lot.",
        "L'expéditeur devrait corriger la distance, car elle augmente artificiellement le CO₂ attribué au lot.",
    ],
    "distance_too_short": [
        "Aucune route n'est plus courte que la ligne droite : la distance doit être corrigée, sinon l'empreinte du lot est sous-estimée.",
        "La distance semble sous-déclarée ; elle doit être corrigée pour ne pas minimiser l'empreinte carbone.",
    ],
    "speed_implausible": [
        "Vérifiez la distance, le mode ou les dates de départ et d'arrivée : l'un d'eux est probablement erroné.",
        "Il faut contrôler la distance et les horaires saisis, la combinaison est physiquement impossible.",
    ],
    "mode_mismatch": [
        "Vérifiez le mode de transport sélectionné : il influence fortement le CO₂ calculé.",
        "Le mode semble mal choisi dans le formulaire ; une correction changerait l'empreinte du lot.",
    ],
    "dates_inconsistent": [
        "Les dates doivent être corrigées : un journal de traçabilité incohérent fait baisser la confiance des consommateurs.",
        "Contrôlez les dates saisies, elles contredisent la chronologie du lot.",
    ],
    "missing_distance": [
        "Renseignez la distance réelle pour que le transport soit pris en compte dans l'empreinte du lot.",
        "Sans distance, le score de transparence du lot est pénalisé : ajoutez-la en modifiant le transport.",
    ],
}

INTROS = {
    "normal": ["Ce transport ne présente pas d'anomalie.", "Le transport est cohérent.", "Aucune anomalie n'a été détectée sur ce transport."],
    "anomaly": ["Ce transport présente une anomalie.", "Le modèle a détecté une incohérence sur ce transport.", "Ce transport paraît anormal."],
}


def explain(t: dict[str, Any], label: str, rng: random.Random | None = None) -> str:
    rng = rng or random.Random(0)
    intro = rng.choice(INTROS["normal" if label == "normal" else "anomaly"])
    return " ".join([intro, *reasons(t, label), rng.choice(ADVICE[label])])
