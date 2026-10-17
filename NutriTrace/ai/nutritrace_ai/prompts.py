"""System prompts and chat messages, shared by training and inference."""

from typing import Any

from .context import render
from .stock import render as render_stock

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

SYSTEM_STOCK = (
    "Tu es l'assistant des distributeurs NutriTrace. Tu réponds en français, en une à trois phrases, uniquement "
    "à partir des DONNÉES DU DISTRIBUTEUR fournies (réceptions, stock, dates limites, ventes). Si l'information "
    "n'y figure pas, dis-le sans rien inventer. Tu ne traites que ces sujets."
)

SYSTEM_RECEPTION = (
    "Tu es le conseiller réception des distributeurs NutriTrace. À partir du RAPPORT DE CONTRÔLE fourni, explique "
    "en français, en deux à quatre phrases, s'il faut accepter, accepter avec réserves ou refuser le lot, et pourquoi. "
    "Cite les éléments du rapport, n'en invente aucun."
)

SYSTEM_CERTIFICATE = (
    "Tu es l'assistant de vérification des certificats de NutriTrace. À partir du RAPPORT DE RISQUE fourni, explique "
    "à l'administrateur, en français et en deux à quatre phrases, pourquoi le certificat paraît fiable ou suspect et "
    "ce qu'il doit vérifier. Cite les éléments du rapport, n'en invente aucun. La décision finale lui appartient."
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


def stock_messages(ctx: dict[str, Any], question: str) -> list[dict[str, str]]:
    return [
        {"role": "system", "content": SYSTEM_STOCK},
        {"role": "user", "content": f"DONNÉES DU DISTRIBUTEUR :\n{render_stock(ctx)}\n\nQUESTION : {question.strip()}"},
    ]


def reception_messages(report: str) -> list[dict[str, str]]:
    return [
        {"role": "system", "content": SYSTEM_RECEPTION},
        {"role": "user", "content": f"RAPPORT DE CONTRÔLE :\n{report}"},
    ]


def certificate_messages(report: str) -> list[dict[str, str]]:
    return [
        {"role": "system", "content": SYSTEM_CERTIFICATE},
        {"role": "user", "content": f"RAPPORT DE RISQUE :\n{report}"},
    ]
