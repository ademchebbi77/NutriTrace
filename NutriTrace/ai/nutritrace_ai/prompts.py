"""System prompts and chat messages, shared by training and inference."""

from typing import Any

from .context import render

SYSTEM_CHAT = (
    "Tu es l'assistant de traçabilité de NutriTrace. Tu réponds en français, en une à trois phrases, "
    "uniquement à partir des DONNÉES DU LOT fournies. Si l'information demandée n'y figure pas, dis-le "
    "clairement sans rien inventer. Tu ne traites que les questions sur ce lot : son origine, son parcours, "
    "ses transports, son empreinte environnementale, ses certifications et la fiabilité de sa traçabilité."
)

SYSTEM_EXPLAIN = (
    "Tu es l'analyste des transports de NutriTrace. À partir du RAPPORT D'ANALYSE fourni, explique en français, "
    "en deux à quatre phrases, pourquoi le transport est jugé normal ou anormal et ce que l'expéditeur devrait "
    "vérifier. Cite les chiffres du rapport, n'en invente aucun."
)


def chat_messages(ctx: dict[str, Any], question: str) -> list[dict[str, str]]:
    return [
        {"role": "system", "content": SYSTEM_CHAT},
        {"role": "user", "content": f"DONNÉES DU LOT :\n{render(ctx)}\n\nQUESTION : {question.strip()}"},
    ]


def explain_messages(report: str) -> list[dict[str, str]]:
    return [
        {"role": "system", "content": SYSTEM_EXPLAIN},
        {"role": "user", "content": f"RAPPORT D'ANALYSE :\n{report}"},
    ]
