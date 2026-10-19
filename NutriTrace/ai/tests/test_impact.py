import random

import pytest

from nutritrace_ai import advisor, impact_check, moderation
from nutritrace_ai import synth_impact as isynth
from nutritrace_ai.impact_models import ImpactCheckModel, ModerationModel
from nutritrace_ai.tabular import build_model

# 1 000 L of olive oil whose traced data already show 300 kg of CO2.
OIL = {"id": 1, "lot": "LOT-2026-001", "product": "Huile d'olive extra vierge", "category": "Huiles et olives", "mass_kg": 1000.0,
       "calculated": {"co2_kg": 300.0, "water_l": 20000.0, "energy_kwh": 900.0}, "declared": {}}


def declare(**values):
    return {**OIL, "declared": {k: {"value": v, "source": "MEASURED"} for k, v in values.items()}}


@pytest.mark.parametrize("declaration, label, reason", [
    (declare(co2_kg=320, packaging_co2_kg=80), "ok", "valeur plausible"),
    (declare(co2_kg=50), "under_declared", "moins de la moitié de ce que les données tracées"),
    (declare(water_l=9000), "under_declared", "20 000 L"),
    (declare(packaging_co2_kg=9000), "too_high", "plus de cinq fois le maximum"),
    (declare(packaging_co2_kg=1), "too_low", "bien en dessous de l'habituel"),
    (declare(waste_kg=50000), "impossible", "plus de dix fois la masse du lot"),
])
def test_declaration_rules(declaration, label, reason):
    results = [(row, impact_check.rule_label(row)) for row in impact_check.rows(declaration)]
    assert impact_check.overall([l for _, l in results]) == label
    assert any(reason in impact_check.reason(r, l) for r, l in results), [impact_check.reason(r, l) for r, l in results]
    assert all(len(impact_check.vector(r)) == len(impact_check.FEATURE_NAMES) for r, _ in results)


def test_the_impact_model_judges_each_figure():
    rng = random.Random(2)
    rows = [row for _ in range(1500) for row in impact_check.rows(isynth.declaration(rng))]
    model = ImpactCheckModel(build_model().fit([impact_check.vector(r) for r in rows], [impact_check.rule_label(r) for r in rows]))
    result = model.predict(declare(co2_kg=50, packaging_co2_kg=80))
    assert result["label"] == "under_declared" and result["is_anomaly"]
    assert {f["indicator"]: f["label"] for f in result["findings"]} == {"co2_kg": "under_declared", "packaging_co2_kg": "ok"}
    assert model.predict({**OIL, "declared": {}})["label"] == "ok"


CONFIG = isynth.CONFIG


def test_advisor_recomputes_the_score_like_nutritrace():
    # 0.6 kg CO2/kg -> sub 64.3; 50 L/kg -> 71.4; 0.5 kWh/kg -> 69.0; 100 km -> 81.1; weighted: 69.8 -> B.
    lot = {"lot": "L1", "product": "Huile", "category": "Huiles et olives", "config": CONFIG, "missing": ["waste_kg"],
           "per_kg": {"co2": 0.6, "water": 50, "energy": 0.5, "food_miles": 100},
           "stages": {"production_co2": 100, "transformation_co2": 300, "transport_co2": 50, "packaging_co2": None},
           "transports": [{"mode": "truck", "distance_km": 100}]}
    result = advisor.analyse(lot)
    assert result["score"] == 70 and result["grade"] == "B" and result["target"] == "A"
    tips = advisor.advice(lot)
    assert tips[0].startswith("Réduire le CO₂ de 0,6 kg CO₂e par kg")
    assert any("La transformation représente 67 %" in tip for tip in tips)
    assert any("Déclarer les déchets" in tip for tip in tips)


def test_advisor_suggests_a_cleaner_transport_mode():
    lot = {"lot": "L2", "product": "Dattes", "category": "Fruits secs et dattes", "config": CONFIG, "missing": [],
           "per_kg": {"co2": 0.9, "water": 100, "energy": 0.1, "food_miles": 800},
           "stages": {"production_co2": 10, "transformation_co2": None, "transport_co2": 90, "packaging_co2": None},
           "transports": [{"mode": "truck", "distance_km": 800}]}
    assert any("Remplacer le camion par le train" in tip and "73 %" in tip for tip in advisor.advice(lot))
    assert advisor.advice({**lot, "per_kg": {"co2": None, "water": None, "energy": None, "food_miles": 0}})[0].startswith("Aucune donnée")


@pytest.mark.parametrize("comment, label", [
    ("Très bonne huile, un peu chère.", "ok"),
    ("Décevant, je ne rachèterai pas.", "ok"),
    ("Contactez-moi à test.user@gmail.com", "personal_data"),
    ("Mon numéro est le 98 765 432", "personal_data"),
    ("Promo sur www.mon-site.tn !", "spam"),
    ("Bande d'escrocs !", "insult"),
])
def test_moderation_rules_and_model(comment, label):
    rng = random.Random(4)
    reviews = [isynth.review(rng) for _ in range(1500)]
    pipeline = moderation.build().fit([r["comment"] for r in reviews], [r["kind"] for r in reviews])
    result = ModerationModel(pipeline).predict({"comment": comment, "rating": 3})
    assert result["label"] == label and result["flagged"] == (label != "ok")
    assert moderation.judge(None, {"comment": "", "rating": 5})["flagged"] is False


def test_synthetic_data_covers_every_label():
    rng = random.Random(1)
    assert {impact_check.overall([impact_check.rule_label(r) for r in impact_check.rows(isynth.declaration(rng))]) for _ in range(800)} == set(impact_check.LABELS)
    assert {isynth.review(rng)["kind"] for _ in range(300)} == set(moderation.LABELS)
