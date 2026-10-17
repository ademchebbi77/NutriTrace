"""Expected shelf life of a lot, to judge its date limit (admin overview of the lots).

A lot is the payload of App\\Services\\Ai\\TransformationAiContext::forLotExpiry: product,
category, unit, method, whether it was transformed, production and expiration dates.
A gradient-boosting regressor predicts the shelf life (in days) from those; the date entered
is then compared with it.
"""

import math
import re
import unicodedata
from datetime import timedelta
from typing import Any

from . import formatting as fmt

# Typical shelf life in days (low, high), by keyword of the product name, the first match wins.
KEYWORDS = [
    ("lait cru", "lait cru", (2, 6)), ("lait", "lait", (3, 10)), ("yaourt", "yaourt", (15, 30)), ("lben", "lben", (5, 12)),
    ("fromage frais", "fromage frais", (10, 30)), ("ricotta", "ricotta", (7, 20)), ("fromage", "fromage", (14, 60)),
    ("huile", "huile d'olive", (365, 730)), ("olives de table", "olives de table", (180, 730)), ("olive", "olives fraîches", (7, 30)),
    ("miel", "miel", (540, 1095)), ("pate de datte", "pâte de dattes", (180, 365)), ("coffret", "dattes en coffret", (240, 450)),
    ("datte", "dattes", (180, 540)), ("amande", "amandes", (270, 540)), ("pistache", "pistaches", (270, 540)),
    ("semoule", "semoule", (270, 540)), ("couscous", "couscous", (270, 540)), ("farine", "farine", (180, 365)), ("ble", "blé", (365, 730)),
    ("concentre", "concentré de tomates", (540, 1095)), ("harissa", "harissa", (365, 900)), ("confiture", "confiture", (365, 730)),
    ("conserve", "conserve", (540, 1095)), ("jus", "jus", (7, 30)),
    ("tomate", "tomates", (7, 21)), ("piment", "piments", (7, 21)), ("orange", "oranges", (20, 60)), ("figue de barbarie", "figues de Barbarie", (10, 30)),
    ("figue", "figues", (3, 10)), ("abricot", "abricots", (7, 15)), ("grenade", "grenades", (30, 90)), ("raisin", "raisins", (7, 30)),
    ("pomme de terre", "pommes de terre", (30, 120)), ("fraise", "fraises", (3, 7)),
]
CATEGORIES = {
    "Fruits et légumes": (7, 45), "Produits laitiers": (5, 40), "Huiles et olives": (180, 730), "Céréales": (270, 540),
    "Miels": (540, 1095), "Fruits secs et dattes": (180, 540), "Conserves et épicerie": (365, 1095),
}
UNITS = ["kg", "t", "L", "unité"]
LABELS = ["plausible", "too_long", "too_short", "missing"]
LABEL_TEXT = {"plausible": "date plausible", "too_long": "date anormalement lointaine", "too_short": "date anormalement proche", "missing": "date limite manquante"}

FEATURE_NAMES = [f"category_{c}" for c in CATEGORIES] + [f"kw_{k.replace(' ', '_')}" for k, _, _ in KEYWORDS] + [f"unit_{u}" for u in UNITS] + ["transformed"]


def normalize(text: str | None) -> str:
    text = unicodedata.normalize("NFD", (text or "").lower())
    return "".join(c for c in text if unicodedata.category(c) != "Mn")


def keyword_of(product: str | None) -> tuple[str, str, tuple[int, int]] | None:
    name = normalize(product)
    for key, label, span in KEYWORDS:
        if re.search(rf"\b{normalize(key)}", name):
            return key, label, span
    return None


def vector(lot: dict[str, Any]) -> list[float]:
    name = normalize(lot.get("product"))
    match = keyword_of(lot.get("product"))
    return ([1.0 if lot.get("category") == c else 0.0 for c in CATEGORIES]
            + [1.0 if match and match[0] == key else 0.0 for key, _, _ in KEYWORDS]
            + [1.0 if lot.get("unit") == u else 0.0 for u in UNITS]
            + [float(bool(lot.get("transformed"))) or float("conditionn" in name)])


def reference(lot: dict[str, Any]) -> tuple[str, tuple[int, int]]:
    """Rule-based typical span, used to build the training targets."""
    match = keyword_of(lot.get("product"))
    if match:
        return match[1], match[2]
    return (lot.get("category") or "produit").lower(), CATEGORIES.get(lot.get("category"), (30, 365))


def actual_days(lot: dict[str, Any]) -> int | None:
    produced, expires = fmt.parse_date(lot.get("production_date")), fmt.parse_date(lot.get("expiration_date"))
    return (expires - produced).days if produced and expires else None


def judge(lot: dict[str, Any], predicted_days: float) -> dict[str, Any]:
    """Compare the date entered with the predicted shelf life."""
    produced = fmt.parse_date(lot.get("production_date"))
    days = actual_days(lot)
    low, high = max(1, round(predicted_days * 0.6)), max(2, round(predicted_days * 1.6))
    if days is None:
        label = "missing"
    elif days > predicted_days * 2.5 and days - predicted_days > 15:
        label = "too_long"
    elif days < predicted_days * 0.35:
        label = "too_short"
    else:
        label = "plausible"
    return {
        "label": label,
        "predicted_days": round(predicted_days),
        "low_days": low,
        "high_days": high,
        "actual_days": days,
        "suggested_date": (produced + timedelta(days=round(predicted_days))).date().isoformat() if produced else None,
    }


def span_text(days: int) -> str:
    if days < 21:
        return f"{days} jours"
    if days < 75:
        return f"{round(days / 7)} semaines"
    if days < 540:
        return f"{round(days / 30)} mois"
    return f"{fmt.number(days / 365, 1)} ans"


def reasons(lot: dict[str, Any], result: dict[str, Any]) -> list[str]:
    kind, _ = reference(lot)
    typical = f"un produit de ce type ({kind}) se conserve en général {span_text(result['low_days'])} à {span_text(result['high_days'])}"
    suggestion = f"Date suggérée : {fmt.date(result['suggested_date'])} (environ {span_text(result['predicted_days'])} après la production)." if result["suggested_date"] else ""
    days = result["actual_days"]
    if result["label"] == "missing":
        return [f"Aucune date limite renseignée, alors qu'{typical}.", suggestion]
    if result["label"] == "too_long":
        return [f"Date limite à {span_text(days)} de la production, alors qu'{typical}.", suggestion]
    if result["label"] == "too_short":
        return [f"Date limite à seulement {span_text(max(days, 0))} de la production, alors qu'{typical}.", suggestion]
    return [f"Date limite à {span_text(days)} de la production, cohérente : {typical}."]


def log_days(days: float) -> float:
    return math.log(max(1.0, days))
