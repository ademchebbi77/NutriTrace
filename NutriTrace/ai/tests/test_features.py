import random

import pytest

from nutritrace_ai import synth
from nutritrace_ai.anomaly import AnomalyDetector, build_model
from nutritrace_ai.explain import explain
from nutritrace_ai.features import FEATURE_NAMES, LABELS, reasons, report, rule_label, vector

# Sfax -> Tunis: about 236 km in a straight line.
BASE = {
    "lot": "LOT-2026-001", "production_date": "2026-05-01", "mode": "truck", "status": "delivered",
    "origin": "Ferme, Sfax", "origin_latitude": 34.7406, "origin_longitude": 10.7603,
    "destination": "Magasin, Tunis", "destination_latitude": 36.8065, "destination_longitude": 10.1815,
    "departure_date": "2026-05-02T06:00:00+00:00", "arrival_date": "2026-05-02T11:00:00+00:00",
    "distance_km": 270.0, "quantity": 1000, "unit": "kg", "mass_kg": 1000, "co2_kg": 28.35,
}


@pytest.mark.parametrize("changes, label", [
    ({}, "normal"),
    ({"distance_km": 900.0, "arrival_date": "2026-05-03T02:00:00+00:00"}, "distance_inflated"),
    ({"distance_km": 120.0}, "distance_too_short"),
    ({"arrival_date": "2026-05-02T07:00:00+00:00"}, "speed_implausible"),
    ({"mode": "plane", "destination_latitude": 34.9, "destination_longitude": 10.76, "distance_km": 25.0}, "mode_mismatch"),
    ({"arrival_date": "2026-05-01T06:00:00+00:00"}, "dates_inconsistent"),
    ({"production_date": "2026-05-10"}, "dates_inconsistent"),
    ({"distance_km": None}, "missing_distance"),
])
def test_rules_label_each_kind_of_transport(changes, label):
    transport = {**BASE, **changes}
    assert rule_label(transport) == label
    assert reasons(transport, label)
    assert len(vector(transport)) == len(FEATURE_NAMES)


def test_in_transit_transport_without_arrival_is_normal():
    assert rule_label({**BASE, "arrival_date": None, "status": "in_transit"}) == "normal"


def test_reasons_quote_the_figures():
    text = " ".join(reasons({**BASE, "distance_km": 900.0, "arrival_date": "2026-05-03T02:00:00+00:00"}, "distance_inflated"))
    assert "900 km" in text and "236 km" in text


def test_the_model_learns_the_labels_and_explains_them():
    rng = random.Random(0)
    normal = synth.normal_transports(rng, 400)
    samples = normal + [synth.inject(rng, rng.choice(normal), kind) for kind in LABELS[1:] for _ in range(80)]
    detector = AnomalyDetector(build_model().fit([vector(t) for t in samples], [rule_label(t) for t in samples]))

    assert detector.predict(BASE)["label"] == "normal"
    result = detector.predict({**BASE, "distance_km": None})
    assert result["is_anomaly"] and result["label"] == "missing_distance" and 0 < result["confidence"] <= 1

    assert "ANOMALIE" in report({**BASE, "distance_km": None}, "missing_distance", 0.9)
    assert explain(BASE, "normal").startswith(("Ce transport", "Le transport", "Aucune anomalie"))
