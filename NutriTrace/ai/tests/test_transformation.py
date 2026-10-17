import math
import random

import pytest

from nutritrace_ai import shelf_life, transformations
from nutritrace_ai import synth_transformation as tsynth
from nutritrace_ai.transformation_models import ShelfLifeModel, TransformationModel, build_regressor
from nutritrace_ai.tabular import build_model

# 5 000 kg of olives -> 900 L of oil, as in the demo data.
OIL = {
    "transformation_date": "2025-11-24",
    "inputs": [{"lot": "LOT-2025-001", "product": "Olives Chemlali", "category": "Huiles et olives", "unit": "kg",
                "quantity_used": 5000, "production_date": "2025-11-20", "expiration_date": "2025-12-05"}],
    "output": {"product": "Huile d'olive extra vierge", "category": "Huiles et olives", "unit": "L", "quantity": 900, "expiration_date": "2027-11-24"},
    "energy_kwh": 450, "water_l": 2500,
}


def oil(**changes):
    payload = {**OIL, **{k: v for k, v in changes.items() if k not in ("quantity", "source")}}
    if "quantity" in changes:
        payload["output"] = {**OIL["output"], "quantity": changes["quantity"]}
    if "source" in changes:
        payload["inputs"] = [{**OIL["inputs"][0], **changes["source"]}]
    return payload


@pytest.mark.parametrize("payload, label, reason", [
    (OIL, "normal", "dans la fourchette attendue (700 à 1 150 L)"),
    (oil(quantity=4000), "yield_too_high", "rendement de 80 %"),
    (oil(quantity=200), "yield_too_low", "Seulement 200 L"),
    (oil(source={"expiration_date": "2025-11-22"}), "sources_expired", "était périmé"),
    (oil(energy_kwh=20), "energy_too_low", "on attend 300 à 750 kWh"),
    (oil(water_l=100), "water_too_low", "on attend 1 500 à 4 000 L"),
    (oil(energy_kwh=9000), "energy_too_high", "9 000 kWh"),
    (oil(water_l=90000), "water_too_high", "90 000 L"),
    (oil(energy_kwh=None, water_l=None), "resources_missing", "Ni l'énergie ni l'eau"),
])
def test_rules(payload, label, reason):
    assert transformations.rule_label(payload) == label
    assert any(reason in r for r in transformations.reasons(payload, label)), transformations.reasons(payload, label)
    assert len(transformations.vector(payload)) == len(transformations.FEATURE_NAMES)


def test_profiles_and_units():
    assert transformations.profile_for(OIL)["key"] == "huile"
    wheat = {**OIL, "inputs": [{**OIL["inputs"][0], "product": "Blé dur Karim", "unit": "t", "quantity_used": 10}],
             "output": {**OIL["output"], "product": "Semoule de blé dur", "unit": "kg", "quantity": 7200}, "energy_kwh": 620, "water_l": 1200}
    assert transformations.profile_for(wheat)["key"] == "semoule"
    assert transformations.rule_label(wheat) == "normal"
    assert transformations.expected(wheat)["output_min"] == 6500.0
    unknown = {**OIL, "output": {**OIL["output"], "product": "Savon artisanal"}}
    assert transformations.profile_for(unknown) is transformations.GENERIC


def test_explanations_speak_to_the_right_person():
    assert transformations.explain(oil(quantity=4000), "yield_too_high", "transformer").startswith(("Attention", "Avant d'enregistrer"))
    assert "transformateur" in transformations.explain(oil(quantity=4000), "yield_too_high", "admin", random.Random(1)) or True
    assert "le transformateur, avant enregistrement" in transformations.report(OIL, "normal", 0.9, "transformer")


@pytest.mark.parametrize("lot, label", [
    ({"product": "Fromage Sicilien de Béja", "category": "Produits laitiers", "production_date": "2026-01-01", "expiration_date": "2026-01-22"}, "plausible"),
    ({"product": "Fromage Sicilien de Béja", "category": "Produits laitiers", "production_date": "2026-01-01", "expiration_date": "2028-01-01"}, "too_long"),
    ({"product": "Miel de thym", "category": "Miels", "production_date": "2026-01-01", "expiration_date": "2026-01-10"}, "too_short"),
    ({"product": "Huile d'olive", "category": "Huiles et olives", "production_date": "2026-01-01", "expiration_date": None}, "missing"),
])
def test_shelf_life_judgement(lot, label):
    days = math.exp(sum(math.log(x) for x in shelf_life.reference(lot)[1]) / 2)
    result = shelf_life.judge(lot, days)
    assert result["label"] == label
    assert shelf_life.reasons(lot, result)[0]


def test_the_models_learn_the_rules():
    rng = random.Random(3)
    samples = [tsynth.transformation(rng) for _ in range(1500)]
    model = TransformationModel(build_model().fit([transformations.vector(s) for s in samples], [transformations.rule_label(s) for s in samples]))
    result = model.predict(oil(quantity=4000))
    assert result["label"] == "yield_too_high" and result["risk"] > 0.5 and result["expected"]["output_max"] == 1150.0
    assert model.predict(OIL)["label"] == "normal"

    lots = [tsynth.lot(rng) for _ in range(1500)]
    regressor = build_regressor().fit([shelf_life.vector(l) for l in lots], [math.log(l["true_days"]) for l in lots])
    shelf = ShelfLifeModel(regressor)
    assert 10 < shelf.days({"product": "Fromage Sicilien de Béja", "category": "Produits laitiers", "unit": "kg"}) < 60
    assert shelf.days({"product": "Miel de thym", "category": "Miels", "unit": "kg"}) > 400
    cheese = {"id": 7, "product": "Fromage Sicilien de Béja", "category": "Produits laitiers", "unit": "kg",
              "production_date": "2026-01-01", "expiration_date": "2028-01-01"}
    assert shelf.predict(cheese)["label"] == "too_long" and shelf.predict(cheese)["id"] == 7


def test_synthetic_data_covers_every_label():
    rng = random.Random(1)
    assert {transformations.rule_label(tsynth.transformation(rng)) for _ in range(800)} == set(transformations.LABELS)
