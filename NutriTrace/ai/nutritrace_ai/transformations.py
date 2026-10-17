"""Check of a transformation: is the output plausible for the inputs, are the energy and
water declared realistic, are the source lots still good? Features, reference rules, reasons.

A transformation is the payload of App\\Services\\Ai\\TransformationAiContext: the inputs
(product, category, quantity, dates), the output (product, quantity, unit) and the resources.
It is checked on the form before saving (for the transformer) and afterwards (for the admin).
"""

import random
import re
import unicodedata
from typing import Any

from . import formatting as fmt

# Reference profiles: yield (output mass / input mass) and resources per kg of input.
# Calibrated on the NutriTrace demo data and usual orders of magnitude.
PROFILES = [
    {"key": "huile", "label": "extraction d'huile d'olive", "input": ["olive"], "output": ["huile"],
     "yield": (0.14, 0.23), "energy": (0.06, 0.15), "water": (0.3, 0.8)},
    {"key": "fromage", "label": "fabrication de fromage", "input": ["lait"], "output": ["fromage"],
     "yield": (0.09, 0.15), "energy": (0.12, 0.25), "water": (0.35, 0.7)},
    {"key": "yaourt", "label": "fabrication de yaourt", "input": ["lait"], "output": ["yaourt", "lben", "raib"],
     "yield": (0.85, 1.0), "energy": (0.08, 0.2), "water": (0.2, 0.6)},
    {"key": "semoule", "label": "mouture de semoule", "input": ["ble"], "output": ["semoule", "farine"],
     "yield": (0.65, 0.78), "energy": (0.04, 0.1), "water": (0.05, 0.25)},
    {"key": "couscous", "label": "fabrication de couscous", "input": ["ble", "semoule"], "output": ["couscous"],
     "yield": (0.62, 0.78), "energy": (0.07, 0.14), "water": (0.2, 0.45)},
    {"key": "concentre", "label": "concentration de tomates", "input": ["tomate"], "output": ["concentre", "double concentre"],
     "yield": (0.13, 0.21), "energy": (0.1, 0.22), "water": (0.6, 1.5)},
    {"key": "harissa", "label": "fabrication de harissa", "input": ["piment"], "output": ["harissa"],
     "yield": (0.6, 0.85), "energy": (0.08, 0.2), "water": (0.3, 0.8)},
    {"key": "conditionnement", "label": "tri et conditionnement", "input": ["datte", "amande", "pistache", "figue", "miel"],
     "output": ["coffret", "conditionne", "barquette", "pot", "boite"], "yield": (0.82, 0.98), "energy": (0.02, 0.08), "water": (0.1, 0.5)},
    {"key": "pate_datte", "label": "fabrication de pâte de dattes", "input": ["datte"], "output": ["pate", "pâte"],
     "yield": (0.75, 0.95), "energy": (0.05, 0.15), "water": (0.1, 0.4)},
    {"key": "jus", "label": "pressage de jus", "input": ["orange", "grenade", "raisin"], "output": ["jus"],
     "yield": (0.35, 0.6), "energy": (0.03, 0.1), "water": (0.2, 0.6)},
    {"key": "confiture", "label": "fabrication de confiture", "input": ["abricot", "figue", "orange", "fraise"], "output": ["confiture"],
     "yield": (0.9, 1.4), "energy": (0.1, 0.25), "water": (0.2, 0.6)},
]
# No known profile: only what physics allows (no mass created, except with added sugar or water).
GENERIC = {"key": "generique", "label": "transformation", "yield": (0.05, 1.05), "energy": (0.005, 1.0), "water": (0.0, 3.0)}

UNIT_KG = {"kg": 1.0, "t": 1000.0, "L": 1.0}

LABELS = ["normal", "yield_too_high", "sources_expired", "yield_too_low", "energy_too_low", "water_too_low",
          "energy_too_high", "water_too_high", "resources_missing"]
LABEL_TEXT = {
    "normal": "aucune anomalie", "yield_too_high": "rendement impossible (quantité produite trop élevée)",
    "sources_expired": "lots sources périmés", "yield_too_low": "rendement anormalement faible",
    "energy_too_low": "énergie sous-déclarée", "water_too_low": "eau sous-déclarée",
    "energy_too_high": "énergie anormalement élevée", "water_too_high": "eau anormalement élevée",
    "resources_missing": "ressources non déclarées",
}

FEATURE_NAMES = [
    "has_profile", "yield", "yield_position", "yield_vs_max", "yield_vs_min", "mass_created",
    "has_energy", "energy_vs_mid", "energy_vs_min", "energy_vs_max",
    "has_water", "water_vs_mid", "water_vs_min", "water_vs_max",
    "inputs", "max_source_age_ratio", "source_expired", "comparable_units",
]


def normalize(text: str | None) -> str:
    text = unicodedata.normalize("NFD", (text or "").lower())
    return "".join(c for c in text if unicodedata.category(c) != "Mn")


def profile_for(t: dict[str, Any]) -> dict[str, Any]:
    inputs = " ".join(normalize(i.get("product")) for i in t.get("inputs") or [])
    output = normalize((t.get("output") or {}).get("product"))
    for profile in PROFILES:
        if any(re.search(rf"\b{normalize(w)}", inputs) for w in profile["input"]) and any(normalize(w) in output for w in profile["output"]):
            return profile
    return GENERIC


def mass(quantity: float | None, unit: str | None) -> float | None:
    factor = UNIT_KG.get(unit or "")
    return None if quantity is None or factor is None else float(quantity) * factor


def facts(t: dict[str, Any]) -> dict[str, Any]:
    profile = profile_for(t)
    inputs = t.get("inputs") or []
    output = t.get("output") or {}
    masses = [mass(i.get("quantity_used"), i.get("unit")) for i in inputs]
    input_mass = sum(m for m in masses if m is not None) if inputs and all(m is not None for m in masses) else None
    output_mass = mass(output.get("quantity"), output.get("unit"))
    yield_ = output_mass / input_mass if input_mass and output_mass is not None else None

    day = fmt.parse_date(t.get("transformation_date"))
    ages, expired = [], []
    for i in inputs:
        produced, expires = fmt.parse_date(i.get("production_date")), fmt.parse_date(i.get("expiration_date"))
        if day and produced and expires and expires > produced:
            ages.append((day - produced).days / max(1, (expires - produced).days))
        if day and expires and expires.date() < day.date():
            expired.append(i)

    energy, water = t.get("energy_kwh"), t.get("water_l")
    per_kg = lambda value: None if value is None or not input_mass else float(value) / input_mass  # noqa: E731
    return {
        "profile": profile,
        "input_mass": input_mass,
        "output_mass": output_mass,
        "yield": yield_,
        "energy": per_kg(energy),
        "water": per_kg(water),
        "energy_declared": energy is not None,
        "water_declared": water is not None,
        "max_age_ratio": max(ages) if ages else None,
        "expired": expired,
        "inputs": len(inputs),
    }


def _ratio(value: float | None, reference: float) -> float:
    return -1.0 if value is None or reference <= 0 else value / reference


def vector(t: dict[str, Any]) -> list[float]:
    f = facts(t)
    p = f["profile"]
    low, high = p["yield"]
    e_low, e_high = p["energy"]
    w_low, w_high = p["water"]
    y = f["yield"]
    return [
        float(p is not GENERIC), -1.0 if y is None else y,
        -1.0 if y is None else (y - low) / (high - low), _ratio(y, high), _ratio(y, low), float(y is not None and y > 1.05),
        float(f["energy_declared"]), _ratio(f["energy"], (e_low + e_high) / 2), _ratio(f["energy"], e_low), _ratio(f["energy"], e_high),
        float(f["water_declared"]), _ratio(f["water"], (w_low + w_high) / 2), _ratio(f["water"], w_low), _ratio(f["water"], w_high),
        float(f["inputs"]), -1.0 if f["max_age_ratio"] is None else f["max_age_ratio"], float(bool(f["expired"])), float(y is not None),
    ]


def rule_label(t: dict[str, Any]) -> str:
    """Reference label, the most serious problem first."""
    f = facts(t)
    p = f["profile"]
    y = f["yield"]
    if y is not None and (y > p["yield"][1] * 1.15 or y > 1.6):
        return "yield_too_high"
    if f["expired"]:
        return "sources_expired"
    if y is not None and y < p["yield"][0] * 0.75:
        return "yield_too_low"
    if f["energy"] is not None and f["energy"] < p["energy"][0] * 0.5:
        return "energy_too_low"
    if f["water"] is not None and p["water"][0] > 0 and f["water"] < p["water"][0] * 0.5:
        return "water_too_low"
    if f["energy"] is not None and f["energy"] > p["energy"][1] * 2:
        return "energy_too_high"
    if f["water"] is not None and f["water"] > p["water"][1] * 2:
        return "water_too_high"
    if not f["energy_declared"] and not f["water_declared"]:
        return "resources_missing"
    return "normal"


def expected(t: dict[str, Any]) -> dict[str, Any]:
    """Expected output and resources for these inputs, shown to the transformer."""
    f = facts(t)
    p = f["profile"]
    out = {"profile": p["label"], "known_profile": p is not GENERIC}
    if f["input_mass"]:
        unit = (t.get("output") or {}).get("unit")
        factor = UNIT_KG.get(unit or "", 1.0)
        out["output_min"] = round(f["input_mass"] * p["yield"][0] / factor, 1)
        out["output_max"] = round(f["input_mass"] * p["yield"][1] / factor, 1)
        out["output_unit"] = unit
        out["energy_min"], out["energy_max"] = round(f["input_mass"] * p["energy"][0]), round(f["input_mass"] * p["energy"][1])
        out["water_min"], out["water_max"] = round(f["input_mass"] * p["water"][0]), round(f["input_mass"] * p["water"][1])
    return out


def reasons(t: dict[str, Any], label: str) -> list[str]:
    f = facts(t)
    p, e = f["profile"], expected(t)
    out_unit = e.get("output_unit") or ""
    produced = fmt.quantity((t.get("output") or {}).get("quantity"), out_unit)
    span = f"{fmt.number(e['output_min'])} à {fmt.number(e['output_max'])} {out_unit}" if "output_min" in e else None
    found: list[str] = []

    if label == "yield_too_high" and span:
        found.append(f"{produced} produits pour {fmt.kg(f['input_mass'])} de matière première, soit un rendement de {fmt.number(f['yield'] * 100)} % ;"
                     f" pour ce type de transformation ({p['label']}), on attend {span}.")
    if label == "yield_too_low" and span:
        found.append(f"Seulement {produced} produits pour {fmt.kg(f['input_mass'])} de matière première ({fmt.number(f['yield'] * 100, 1)} %) ;"
                     f" pour ce type de transformation ({p['label']}), on attend {span}.")
    if label == "sources_expired":
        for i in f["expired"]:
            found.append(f"Le lot source {i.get('lot')} ({i.get('product')}) était périmé le jour de la transformation (date limite {fmt.date(i.get('expiration_date'))}).")
    if label in ("energy_too_low", "energy_too_high"):
        found.append(f"{fmt.number((t.get('energy_kwh') or 0))} kWh déclarés ; pour cette quantité, on attend {fmt.number(e.get('energy_min'))} à {fmt.number(e.get('energy_max'))} kWh.")
    if label in ("water_too_low", "water_too_high"):
        found.append(f"{fmt.number((t.get('water_l') or 0))} L d'eau déclarés ; pour cette quantité, on attend {fmt.number(e.get('water_min'))} à {fmt.number(e.get('water_max'))} L.")
    if label == "resources_missing":
        found.append("Ni l'énergie ni l'eau utilisées ne sont déclarées : l'empreinte du nouveau lot sera incomplète.")
    if label == "normal":
        if span:
            found.append(f"{produced} produits, dans la fourchette attendue ({span}) pour ce type de transformation ({p['label']}).")
        if f["energy_declared"] or f["water_declared"]:
            found.append("Énergie et eau déclarées cohérentes avec la quantité transformée.")
        if not found:
            found.append("Quantités et ressources cohérentes.")
    if f["max_age_ratio"] is not None and f["max_age_ratio"] > 0.8 and label != "sources_expired":
        found.append("Un lot source était proche de sa date limite le jour de la transformation.")
    if p is GENERIC and label != "normal":
        found.append("Pas de profil de référence pour ces produits : seuls des seuils larges sont appliqués.")
    return found


def report(t: dict[str, Any], label: str, confidence: float, audience: str = "transformer") -> str:
    f = facts(t)
    inputs = " + ".join(f"{fmt.quantity(i.get('quantity_used'), i.get('unit', ''))} de {i.get('product')} (lot {i.get('lot')})" for i in t.get("inputs") or [])
    output = t.get("output") or {}
    lines = [
        f"Transformation du {fmt.date(t.get('transformation_date'))} : {inputs} → {fmt.quantity(output.get('quantity'), output.get('unit', ''))} de {output.get('product')}.",
        f"Énergie : {fmt.number(t['energy_kwh']) + ' kWh' if t.get('energy_kwh') is not None else 'non déclarée'}. Eau : {fmt.number(t['water_l']) + ' L' if t.get('water_l') is not None else 'non déclarée'}.",
        f"Profil de référence : {f['profile']['label']}.",
        f"Verdict du modèle : {'NORMAL' if label == 'normal' else 'ANOMALIE'} ({LABEL_TEXT[label]}), confiance {round(confidence * 100)} %.",
        "Éléments : " + " ".join(reasons(t, label)),
        f"Destinataire : {'le transformateur, avant enregistrement' if audience == 'transformer' else 'l’administrateur, qui contrôle les transformations'}.",
    ]
    return "\n".join(lines)


ADVICE = {
    "transformer": {
        "normal": ["Vous pouvez enregistrer la transformation.", "Rien ne s'oppose à l'enregistrement."],
        "yield_too_high": ["Vérifiez la quantité produite et l'unité choisie avant d'enregistrer : une transformation ne peut plus être modifiée.",
                           "Corrigez la quantité produite si elle est erronée : enregistrée telle quelle, elle serait signalée comme suspecte."],
        "sources_expired": ["Vérifiez les lots sources choisis : un produit périmé ne devrait pas être transformé.", "Choisissez d'autres lots sources ou vérifiez leurs dates limites."],
        "yield_too_low": ["Vérifiez la quantité produite ou les quantités de matière première utilisées.", "Une perte importante est possible, mais vérifiez les quantités avant d'enregistrer."],
        "energy_too_low": ["Vérifiez l'énergie déclarée : une valeur trop basse fausse l'empreinte du lot.", "Corrigez l'énergie si elle est sous-estimée, sinon l'empreinte paraîtra meilleure qu'en réalité."],
        "water_too_low": ["Vérifiez l'eau déclarée : une valeur trop basse fausse l'empreinte du lot.", "Corrigez l'eau utilisée si elle est sous-estimée."],
        "energy_too_high": ["Vérifiez l'unité de l'énergie saisie (kWh).", "La valeur est peut-être une erreur de saisie : vérifiez-la."],
        "water_too_high": ["Vérifiez l'unité de l'eau saisie (litres).", "La valeur est peut-être une erreur de saisie : vérifiez-la."],
        "resources_missing": ["Ajoutez l'énergie et l'eau utilisées si vous les connaissez.", "Déclarer ces ressources améliore le score de transparence du lot."],
    },
    "admin": {
        "normal": ["Aucune action n'est nécessaire.", "Cette transformation peut être considérée comme fiable."],
        "yield_too_high": ["Contactez le transformateur : une quantité produite supérieure au possible peut cacher l'ajout de matière non tracée.",
                           "Ce cas mérite une vérification auprès du transformateur, car il pourrait masquer des produits d'origine inconnue."],
        "sources_expired": ["Vérifiez avec le transformateur l'utilisation de lots périmés.", "Un contrôle est conseillé sur la qualité du lot produit."],
        "yield_too_low": ["Vérifiez les quantités avec le transformateur : une partie de la matière n'est peut-être pas tracée.", "Une perte importante mérite une vérification."],
        "energy_too_low": ["L'empreinte du lot est probablement sous-estimée : demandez une correction des données.", "Possible écoblanchiment : vérifiez les ressources déclarées."],
        "water_too_low": ["L'empreinte en eau du lot est probablement sous-estimée.", "Possible écoblanchiment : vérifiez les ressources déclarées."],
        "energy_too_high": ["Probable erreur de saisie à faire corriger.", "Vérifiez l'unité utilisée avec le transformateur."],
        "water_too_high": ["Probable erreur de saisie à faire corriger.", "Vérifiez l'unité utilisée avec le transformateur."],
        "resources_missing": ["Encouragez le transformateur à déclarer ses ressources.", "L'empreinte du lot reste incomplète tant que ces données manquent."],
    },
}
INTROS = {
    "transformer": {"normal": ["Votre transformation est cohérente.", "Les quantités saisies sont plausibles."],
                    "anomaly": ["Attention, un point de votre saisie paraît anormal.", "Avant d'enregistrer, vérifiez ce point."]},
    "admin": {"normal": ["Cette transformation ne présente pas d'anomalie.", "Transformation cohérente."],
              "anomaly": ["Cette transformation présente une anomalie.", "Un point de cette transformation est suspect."]},
}


def explain(t: dict[str, Any], label: str, audience: str = "transformer", rng: random.Random | None = None) -> str:
    rng = rng or random.Random(0)
    intro = rng.choice(INTROS[audience]["normal" if label == "normal" else "anomaly"])
    return " ".join([intro, *reasons(t, label)[:3], rng.choice(ADVICE[audience][label])])


def risk(probabilities: dict[str, float]) -> float:
    """0 = normal, 1 = surely anomalous; ressources manquantes counts half."""
    value = sum(p for label, p in probabilities.items() if label not in ("normal", "resources_missing"))
    return round(min(1.0, value + 0.5 * probabilities.get("resources_missing", 0.0)), 4)
