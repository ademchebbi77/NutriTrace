"""Synthetic transformations and lots in the format of the Laravel export.

Normal transformations follow the reference profiles; anomalous ones get an inflated or
deflated output, expired sources or wrong resources, so the model sees every case.
"""

import random
from datetime import datetime, timedelta
from typing import Any

from . import shelf_life, transformations
from .transformations import PROFILES

# Products used for each profile: (input product, input unit, input category), (output product, output unit, output category).
PRODUCTS = {
    "huile": [(("Olives Chemlali", "kg", "Huiles et olives"), ("Huile d'olive extra vierge", "L", "Huiles et olives")),
              (("Olives Chetoui", "kg", "Huiles et olives"), ("Huile d'olive vierge", "L", "Huiles et olives"))],
    "fromage": [(("Lait cru de vache", "L", "Produits laitiers"), ("Fromage Sicilien de Béja", "kg", "Produits laitiers")),
                (("Lait de chèvre", "L", "Produits laitiers"), ("Fromage frais de chèvre", "kg", "Produits laitiers"))],
    "yaourt": [(("Lait cru de vache", "L", "Produits laitiers"), ("Yaourt nature", "kg", "Produits laitiers"))],
    "semoule": [(("Blé dur Karim", "t", "Céréales"), ("Semoule de blé dur", "kg", "Céréales")), (("Blé tendre", "t", "Céréales"), ("Farine de blé", "kg", "Céréales"))],
    "couscous": [(("Blé dur Karim", "t", "Céréales"), ("Couscous moyen", "kg", "Céréales")), (("Semoule de blé dur", "kg", "Céréales"), ("Couscous fin", "kg", "Céréales"))],
    "concentre": [(("Tomates de plein champ", "kg", "Fruits et légumes"), ("Concentré de tomates", "kg", "Conserves et épicerie"))],
    "harissa": [(("Piments Baklouti", "kg", "Fruits et légumes"), ("Harissa traditionnelle", "kg", "Conserves et épicerie"))],
    "conditionnement": [(("Dattes Deglet Nour", "t", "Fruits secs et dattes"), ("Dattes Deglet Nour en coffret", "kg", "Fruits secs et dattes")),
                        (("Amandes de Kairouan", "kg", "Fruits secs et dattes"), ("Amandes en barquette", "kg", "Fruits secs et dattes")),
                        (("Miel de thym", "kg", "Miels"), ("Miel de thym en pot", "kg", "Miels"))],
    "pate_datte": [(("Dattes Deglet Nour", "kg", "Fruits secs et dattes"), ("Pâte de dattes", "kg", "Fruits secs et dattes"))],
    "jus": [(("Oranges Maltaises", "kg", "Fruits et légumes"), ("Jus d'orange frais", "L", "Fruits et légumes")),
            (("Grenades de Testour", "kg", "Fruits et légumes"), ("Jus de grenade", "L", "Fruits et légumes"))],
    "confiture": [(("Abricots de Kairouan", "kg", "Fruits et légumes"), ("Confiture d'abricots", "kg", "Conserves et épicerie")),
                  (("Figues de Barbarie", "kg", "Fruits et légumes"), ("Confiture de figues", "kg", "Conserves et épicerie"))],
}
# Unusual pairs: no reference profile, only the generic limits apply.
UNKNOWN = [(("Pommes de terre", "kg", "Fruits et légumes"), ("Chips artisanales", "kg", "Conserves et épicerie")),
           (("Grenades de Testour", "kg", "Fruits et légumes"), ("Mélasse de grenade", "kg", "Conserves et épicerie"))]
UNIT_KG = {"kg": 1.0, "t": 1000.0, "L": 1.0}
INPUT_RANGES = {"kg": (200, 8000), "L": (300, 5000), "t": (2, 15)}


def day(moment: datetime) -> str:
    return moment.strftime("%Y-%m-%d")


def lot_number(rng: random.Random, year: int) -> str:
    return f"LOT-{year}-{rng.randint(1, 999):03d}"


def transformation(rng: random.Random, kind: str | None = None) -> dict[str, Any]:
    """A transformation, normal or with one anomaly (kind: a label of transformations.LABELS)."""
    profile = rng.choice(PROFILES + [transformations.GENERIC]) if rng.random() < 0.97 else transformations.GENERIC
    pairs = PRODUCTS.get(profile["key"]) or UNKNOWN
    (in_name, in_unit, in_cat), (out_name, out_unit, out_cat) = rng.choice(pairs)
    when = datetime(rng.choice([2025, 2026]), rng.randint(1, 12), rng.randint(1, 28))

    inputs = []
    for _ in range(rng.choices([1, 2, 3], weights=[75, 20, 5])[0]):
        low, high = INPUT_RANGES[in_unit]
        quantity = float(rng.randint(low, high))
        shelf_low, shelf_high = shelf_life.reference({"product": in_name, "category": in_cat})[1]
        shelf = rng.randint(shelf_low, shelf_high)
        produced = when - timedelta(days=rng.randint(0, max(1, int(shelf * 0.6))))
        inputs.append({"lot": lot_number(rng, produced.year), "product": in_name, "category": in_cat, "unit": in_unit,
                       "quantity_used": quantity, "production_date": day(produced),
                       "expiration_date": day(produced + timedelta(days=shelf)) if rng.random() > 0.1 else None})

    input_mass = sum(i["quantity_used"] * UNIT_KG[i["unit"]] for i in inputs)
    y_low, y_high = profile["yield"]
    e_low, e_high = profile["energy"]
    w_low, w_high = profile["water"]
    yield_ = rng.uniform(y_low, y_high) if profile is not transformations.GENERIC else rng.uniform(0.2, 0.9)
    energy = input_mass * rng.uniform(e_low, e_high) if profile is not transformations.GENERIC else input_mass * rng.uniform(0.02, 0.4)
    water = input_mass * rng.uniform(w_low, w_high) if profile is not transformations.GENERIC else input_mass * rng.uniform(0.0, 1.5)
    energy_declared, water_declared = rng.random() > 0.1, rng.random() > 0.15

    kind = kind or rng.choices(transformations.LABELS, weights=[46, 10, 6, 8, 8, 6, 5, 5, 6])[0]
    if kind == "yield_too_high":
        yield_ = rng.choice([y_high * rng.uniform(1.3, 4), rng.uniform(1.7, 6)])
    elif kind == "yield_too_low":
        yield_ = y_low * rng.uniform(0.1, 0.65)
    elif kind == "sources_expired":
        source = rng.choice(inputs)
        produced = when - timedelta(days=rng.randint(30, 400))
        source["production_date"] = day(produced)
        source["expiration_date"] = day(when - timedelta(days=rng.randint(1, 25)))
    elif kind == "energy_too_low":
        energy, energy_declared = input_mass * e_low * rng.uniform(0.01, 0.4), True
    elif kind == "water_too_low" and w_low > 0:
        water, water_declared = input_mass * w_low * rng.uniform(0.0, 0.4), True
    elif kind == "energy_too_high":
        energy, energy_declared = input_mass * e_high * rng.uniform(2.5, 20), True
    elif kind == "water_too_high":
        water, water_declared = input_mass * w_high * rng.uniform(2.5, 20), True
    elif kind == "resources_missing":
        energy_declared = water_declared = False

    out_quantity = round(input_mass * yield_ / UNIT_KG[out_unit], 1)
    shelf_low, shelf_high = shelf_life.reference({"product": out_name, "category": out_cat})[1]
    return {
        "id": rng.randint(1, 10_000),
        "transformation_date": day(when),
        "inputs": inputs,
        "output": {"product": out_name, "category": out_cat, "unit": out_unit, "quantity": out_quantity,
                   "expiration_date": day(when + timedelta(days=rng.randint(shelf_low, shelf_high)))},
        "energy_kwh": round(energy, 1) if energy_declared else None,
        "water_l": round(water, 1) if water_declared else None,
        "process_description": "Transformation artisanale.",
    }


def lot(rng: random.Random) -> dict[str, Any]:
    """A lot with a date limit that is plausible, too far, too close or missing."""
    key, label, (low, high) = rng.choice(shelf_life.KEYWORDS)
    names = {"lait cru": "Lait cru de vache", "huile": "Huile d'olive extra vierge", "olive": "Olives Chemlali", "ble": "Blé dur Karim",
             "coffret": "Dattes Deglet Nour en coffret", "concentre": "Concentré de tomates", "pate de datte": "Pâte de dattes"}
    product = names.get(key, label[0].upper() + label[1:])
    category = next((c for c, (cl, ch) in shelf_life.CATEGORIES.items() if cl <= (low + high) / 2 <= ch), "Fruits et légumes")
    for word, cat in (("lait", "Produits laitiers"), ("fromage", "Produits laitiers"), ("yaourt", "Produits laitiers"), ("huile", "Huiles et olives"),
                      ("olive", "Huiles et olives"), ("miel", "Miels"), ("datte", "Fruits secs et dattes"), ("amande", "Fruits secs et dattes"),
                      ("pistache", "Fruits secs et dattes"), ("semoule", "Céréales"), ("couscous", "Céréales"), ("ble", "Céréales"), ("farine", "Céréales"),
                      ("concentre", "Conserves et épicerie"), ("harissa", "Conserves et épicerie"), ("confiture", "Conserves et épicerie"), ("conserve", "Conserves et épicerie")):
        if word in key:
            category = cat
            break
    produced = datetime(rng.choice([2025, 2026]), rng.randint(1, 12), rng.randint(1, 28))
    days = rng.randint(low, high)
    return {
        "id": rng.randint(1, 10_000), "lot": lot_number(rng, produced.year), "product": product, "category": category,
        "unit": rng.choice(["kg", "kg", "t", "L"]) if "lait" not in key and "huile" not in key else "L",
        # Mostly true for processed products, but not always: the product type must matter most.
        "transformed": rng.random() < (0.8 if key in ("huile", "fromage", "fromage frais", "yaourt", "semoule", "couscous", "farine", "concentre",
                                                      "harissa", "confiture", "coffret", "pate de datte", "jus") else 0.1),
        "production_date": day(produced), "expiration_date": day(produced + timedelta(days=days)), "true_days": days,
    }
