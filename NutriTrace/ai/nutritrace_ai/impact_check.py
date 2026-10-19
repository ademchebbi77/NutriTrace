"""Check of the environmental figures a producer or transformer declares for a lot.

A declaration is the payload of App\\Services\\Ai\\ImpactAiContext::forDeclaration: the lot
(product, category, mass), what NutriTrace already computes from the traced data
(production, transformation and transport figures), and the declared values with their source.

Each declared figure is judged on its own (one row per indicator for the model):
- declared below what the traced data already shows: under-declared, a greenwashing risk;
- far below what is usual for the product: suspiciously low;
- far above: probably a typing or unit error;
- waste heavier than the lot itself many times over: impossible.
"""

import math
import random
from typing import Any

from . import formatting as fmt

INDICATORS = ["co2_kg", "water_l", "energy_kwh", "waste_kg", "packaging_co2_kg"]
INDICATOR_TEXT = {"co2_kg": "le CO₂", "water_l": "l'eau", "energy_kwh": "l'énergie", "waste_kg": "les déchets", "packaging_co2_kg": "le CO₂ de l'emballage"}
UNITS = {"co2_kg": "kg CO₂e", "water_l": "L", "energy_kwh": "kWh", "waste_kg": "kg", "packaging_co2_kg": "kg CO₂e"}
# Traced data gives a floor for these: NutriTrace already counts what the actors recorded.
FLOORED = {"co2_kg", "water_l", "energy_kwh"}

# Usual values per kg of product, NutriTrace scale (farm and plant resources, not full life cycle).
CATEGORIES = ["Huiles et olives", "Produits laitiers", "Céréales", "Fruits et légumes", "Miels", "Fruits secs et dattes", "Conserves et épicerie"]
TYPICAL = {
    "Huiles et olives": {"co2_kg": (0.03, 1.5), "water_l": (2, 60), "energy_kwh": (0.05, 3), "waste_kg": (0.05, 2), "packaging_co2_kg": (0.02, 0.5)},
    "Produits laitiers": {"co2_kg": (0.1, 2.5), "water_l": (1, 60), "energy_kwh": (0.1, 3), "waste_kg": (0.01, 8), "packaging_co2_kg": (0.01, 0.3)},
    "Céréales": {"co2_kg": (0.1, 1.0), "water_l": (0, 5), "energy_kwh": (0.03, 0.5), "waste_kg": (0.01, 0.4), "packaging_co2_kg": (0.01, 0.15)},
    "Fruits et légumes": {"co2_kg": (0.05, 0.6), "water_l": (5, 150), "energy_kwh": (0.02, 0.4), "waste_kg": (0.01, 0.3), "packaging_co2_kg": (0.01, 0.15)},
    "Miels": {"co2_kg": (0.02, 0.5), "water_l": (0, 5), "energy_kwh": (0.03, 0.3), "waste_kg": (0.001, 0.1), "packaging_co2_kg": (0.02, 0.4)},
    "Fruits secs et dattes": {"co2_kg": (0.05, 0.8), "water_l": (20, 300), "energy_kwh": (0.02, 0.4), "waste_kg": (0.01, 0.3), "packaging_co2_kg": (0.01, 0.3)},
    "Conserves et épicerie": {"co2_kg": (0.2, 3.0), "water_l": (10, 500), "energy_kwh": (0.1, 2.5), "waste_kg": (0.02, 1.0), "packaging_co2_kg": (0.05, 0.6)},
}
DEFAULT = {"co2_kg": (0.03, 3.0), "water_l": (0, 500), "energy_kwh": (0.02, 3), "waste_kg": (0.001, 2), "packaging_co2_kg": (0.01, 0.6)}

LABELS = ["ok", "impossible", "under_declared", "too_high", "too_low"]
LABEL_TEXT = {"ok": "valeur plausible", "impossible": "valeur impossible", "under_declared": "valeur sous-déclarée",
              "too_high": "valeur anormalement élevée", "too_low": "valeur anormalement basse"}
SEVERITY = ["impossible", "under_declared", "too_high", "too_low", "ok"]

FEATURE_NAMES = [f"ind_{i}" for i in INDICATORS] + [f"cat_{c}" for c in CATEGORIES] + [
    "log_per_kg", "log_vs_low", "log_vs_high", "has_calculated", "log_vs_calculated", "measured", "zero",
]


def _log(value: float) -> float:
    return math.log10(max(value, 1e-6))


def typical(category: str | None, indicator: str) -> tuple[float, float]:
    return TYPICAL.get(category or "", DEFAULT).get(indicator, DEFAULT[indicator])


def rows(declaration: dict[str, Any]) -> list[dict[str, Any]]:
    """One row per declared indicator, with what the model needs to judge it."""
    mass = declaration.get("mass_kg") or 0
    calculated = declaration.get("calculated") or {}
    out = []
    for indicator, item in (declaration.get("declared") or {}).items():
        if indicator not in INDICATORS or item is None:
            continue
        value = float(item.get("value") or 0)
        calc = calculated.get(indicator)
        out.append({
            "indicator": indicator, "category": declaration.get("category"), "value": value, "mass_kg": mass,
            "per_kg": value / mass if mass else None, "calculated": float(calc) if calc is not None else None,
            "source": item.get("source"),
        })
    return out


def vector(row: dict[str, Any]) -> list[float]:
    low, high = typical(row["category"], row["indicator"])
    per_kg = row["per_kg"]
    calc = row["calculated"]
    has_calc = calc is not None and calc > 0
    return ([1.0 if row["indicator"] == i else 0.0 for i in INDICATORS]
            + [1.0 if row["category"] == c else 0.0 for c in CATEGORIES]
            + [-7.0 if per_kg is None else _log(per_kg),
               0.0 if per_kg is None or low <= 0 else _log(per_kg) - _log(low),
               0.0 if per_kg is None else _log(per_kg) - _log(high),
               float(has_calc),
               _log(row["value"]) - _log(calc) if has_calc else 0.0,
               float(row["source"] == "MEASURED"),
               float(row["value"] == 0)])


def rule_label(row: dict[str, Any]) -> str:
    low, high = typical(row["category"], row["indicator"])
    per_kg, calc = row["per_kg"], row["calculated"]
    if row["indicator"] == "waste_kg" and per_kg is not None and per_kg > 10:
        return "impossible"
    if row["indicator"] in FLOORED and calc is not None and calc > 0 and row["value"] < 0.5 * calc:
        return "under_declared"
    if per_kg is not None and per_kg > high * 5:
        return "too_high"
    if per_kg is not None and low > 0 and per_kg < low * 0.2 and not (row["indicator"] in FLOORED and calc and row["value"] >= 0.5 * calc):
        return "too_low"
    return "ok"


def overall(labels: list[str]) -> str:
    return min(labels, key=SEVERITY.index) if labels else "ok"


def reason(row: dict[str, Any], label: str) -> str:
    name, unit = INDICATOR_TEXT[row["indicator"]], UNITS[row["indicator"]]
    low, high = typical(row["category"], row["indicator"])
    value = f"{fmt.smart(row['value'])} {unit}"
    per_kg = f"{fmt.smart(row['per_kg'])} {unit} par kg" if row["per_kg"] is not None else "?"
    usual = f"{fmt.smart(low)} à {fmt.smart(high)} {unit} par kg"
    if label == "impossible":
        return f"Déchets déclarés : {value}, soit {per_kg} : plus de dix fois la masse du lot, c'est impossible."
    if label == "under_declared":
        return (f"Pour {name}, {value} déclarés, moins de la moitié de ce que les données tracées du lot montrent déjà "
                f"({fmt.smart(row['calculated'])} {unit}) : risque d'écoblanchiment.")
    if label == "too_high":
        return f"Pour {name}, {value} déclarés ({per_kg}), plus de cinq fois le maximum habituel pour ce type de produit ({usual}) : erreur d'unité ou de saisie ?"
    if label == "too_low":
        return f"Pour {name}, {value} déclarés ({per_kg}), bien en dessous de l'habituel pour ce type de produit ({usual})."
    return f"Pour {name}, {value} déclarés ({per_kg}), valeur plausible."


def report(declaration: dict[str, Any], results: list[tuple[dict[str, Any], str]], label: str, confidence: float) -> str:
    lines = [
        f"Déclaration environnementale du lot {declaration.get('lot')} ({declaration.get('product')}, catégorie {declaration.get('category')},"
        f" {fmt.kg(declaration.get('mass_kg'))}).",
        "Valeurs déclarées : " + " ; ".join(
            f"{INDICATOR_TEXT[r['indicator']]} {fmt.smart(r['value'])} {UNITS[r['indicator']]} ({'mesuré' if r['source'] == 'MEASURED' else 'fourni'})"
            for r, _ in results) + ".",
        f"Verdict du modèle : {'PLAUSIBLE' if label == 'ok' else 'À VÉRIFIER'} ({LABEL_TEXT[label]}), confiance {round(confidence * 100)} %.",
        "Éléments : " + " ".join(reason(r, l) for r, l in results if l != "ok") if label != "ok" else "Éléments : toutes les valeurs sont plausibles.",
    ]
    return "\n".join(lines)


ADVICE = {
    "ok": ["Vous pouvez enregistrer ces valeurs.", "Ces données amélioreront la transparence du lot."],
    "impossible": ["Corrigez cette valeur avant d'enregistrer : elle est physiquement impossible.", "Vérifiez l'unité saisie, la valeur ne peut pas être exacte."],
    "under_declared": ["Vérifiez ces chiffres : une empreinte inférieure aux données tracées serait signalée comme de l'écoblanchiment.",
                       "Déclarez au moins ce que les données tracées montrent déjà, sinon l'empreinte paraîtra meilleure qu'en réalité."],
    "too_high": ["Vérifiez l'unité saisie (kg, L, kWh) : la valeur semble trop élevée.", "Il s'agit probablement d'une erreur de saisie, vérifiez-la."],
    "too_low": ["Vérifiez cette valeur : si elle est exacte, gardez un justificatif de la mesure.", "Une valeur aussi basse doit pouvoir être justifiée."],
}
INTROS = {"ok": ["Votre déclaration est cohérente.", "Les valeurs déclarées sont plausibles."],
          "anomaly": ["Attention, une valeur déclarée paraît anormale.", "Avant d'enregistrer, vérifiez cette déclaration."]}


def explain(results: list[tuple[dict[str, Any], str]], label: str, rng: random.Random | None = None) -> str:
    rng = rng or random.Random(0)
    reasons = [reason(r, l) for r, l in results if l != "ok"][:3]
    return " ".join([rng.choice(INTROS["ok" if label == "ok" else "anomaly"]), *reasons, rng.choice(ADVICE[label])])
