"""Features of a transport for the anomaly model, and the reference rules used to label
the training examples.

A transport is the payload of App\\Services\\Ai\\AiContext::forTransport.

The rules give the ground truth of the synthetic training set; the model learns from
those examples and is what the service actually uses, so it also scores the borderline
cases the rules cut sharply (see scripts/train_anomaly.py for its measured accuracy).
"""

from math import log1p
from typing import Any

from . import formatting as fmt
from .geo import haversine_km

MODES = ["truck", "van", "train", "ship", "plane"]

# Highest plausible average speed, door to door, in km/h.
MAX_SPEED_KMH = {"truck": 90, "van": 100, "train": 160, "ship": 45, "plane": 800}
# A plane or a ship below this distance makes no sense for food logistics.
MIN_DISTANCE_KM = {"plane": 150, "ship": 30}
# Roads are longer than the straight line: beyond this factor the distance is suspicious.
MAX_ROAD_FACTOR = 2.5
# Below the straight line is impossible; a small margin absorbs rounding.
MIN_ROAD_FACTOR = 0.9
# Under this straight-line distance (same town), ratios mean nothing.
SAME_PLACE_KM = 5
SAME_PLACE_MAX_KM = 60

LABELS = [
    "normal",
    "distance_inflated",
    "distance_too_short",
    "speed_implausible",
    "mode_mismatch",
    "dates_inconsistent",
    "missing_distance",
]

FEATURE_NAMES = [f"mode_{m}" for m in MODES] + [
    "has_distance",
    "distance_km",
    "has_coordinates",
    "straight_km",
    "road_factor",
    "has_arrival",
    "duration_h",
    "speed_kmh",
    "speed_ratio",
    "min_distance_ratio",
    "arrival_before_departure",
    "departure_before_production",
    "log_mass_kg",
]


def facts(t: dict[str, Any]) -> dict[str, Any]:
    """Derived values of a transport, None when unknown."""
    coords = [t.get(k) for k in ("origin_latitude", "origin_longitude", "destination_latitude", "destination_longitude")]
    straight = haversine_km(*coords) if all(c is not None for c in coords) else None
    distance = t.get("distance_km")
    departure, arrival = fmt.parse_date(t.get("departure_date")), fmt.parse_date(t.get("arrival_date"))
    production = fmt.parse_date(t.get("production_date"))

    duration = (arrival - departure).total_seconds() / 3600 if departure and arrival else None
    speed = distance / duration if distance is not None and duration and duration > 0 else None

    return {
        "mode": t.get("mode"),
        "distance": distance,
        "straight": straight,
        "road_factor": distance / straight if distance is not None and straight and straight >= SAME_PLACE_KM else None,
        "duration": duration,
        "speed": speed,
        "arrival_before_departure": duration is not None and duration < 0,
        "departure_before_production": bool(departure and production and departure.date() < production.date()),
        "mass": t.get("mass_kg"),
    }


def vector(t: dict[str, Any]) -> list[float]:
    """Numeric features in FEATURE_NAMES order; -1 stands for unknown."""
    f = facts(t)
    unknown = -1.0
    mode = f["mode"]
    max_speed = MAX_SPEED_KMH.get(mode, 100)

    return [1.0 if mode == m else 0.0 for m in MODES] + [
        1.0 if f["distance"] is not None else 0.0,
        f["distance"] if f["distance"] is not None else unknown,
        1.0 if f["straight"] is not None else 0.0,
        f["straight"] if f["straight"] is not None else unknown,
        f["road_factor"] if f["road_factor"] is not None else unknown,
        1.0 if f["duration"] is not None else 0.0,
        f["duration"] if f["duration"] is not None else unknown,
        f["speed"] if f["speed"] is not None else unknown,
        f["speed"] / max_speed if f["speed"] is not None else unknown,
        f["distance"] / MIN_DISTANCE_KM[mode] if mode in MIN_DISTANCE_KM and f["distance"] is not None else unknown,
        1.0 if f["arrival_before_departure"] else 0.0,
        1.0 if f["departure_before_production"] else 0.0,
        log1p(f["mass"]) if f["mass"] else unknown,
    ]


def rule_label(t: dict[str, Any]) -> str:
    """Reference label, the most serious problem first."""
    f = facts(t)
    if f["arrival_before_departure"] or f["departure_before_production"]:
        return "dates_inconsistent"
    if f["distance"] is None:
        return "missing_distance"
    if f["road_factor"] is not None and f["road_factor"] < MIN_ROAD_FACTOR:
        return "distance_too_short"
    if f["speed"] is not None and f["speed"] > MAX_SPEED_KMH.get(f["mode"], 100):
        return "speed_implausible"
    if f["mode"] in MIN_DISTANCE_KM and f["distance"] < MIN_DISTANCE_KM[f["mode"]]:
        return "mode_mismatch"
    if (f["road_factor"] is not None and f["road_factor"] > MAX_ROAD_FACTOR) or (
        f["straight"] is not None and f["straight"] < SAME_PLACE_KM and f["distance"] > SAME_PLACE_MAX_KM
    ):
        return "distance_inflated"
    return "normal"


def reasons(t: dict[str, Any], label: str) -> list[str]:
    """Facts behind a label, in French, with the figures."""
    f = facts(t)
    mode = fmt.mode(f["mode"]).lower()
    out: list[str] = []

    if label == "dates_inconsistent":
        if f["arrival_before_departure"]:
            out.append(f"Arrivée ({fmt.datetime_fr(t.get('arrival_date'))}) datée avant le départ ({fmt.datetime_fr(t.get('departure_date'))}).")
        if f["departure_before_production"]:
            out.append(f"Départ ({fmt.date(t.get('departure_date'))}) antérieur à la production du lot ({fmt.date(t.get('production_date'))}).")
    elif label == "missing_distance":
        out.append("Aucune distance renseignée : l'empreinte carbone du trajet ne peut pas être calculée.")
    elif label == "distance_too_short" and f["straight"]:
        out.append(f"Distance déclarée {fmt.km(f['distance'])}, plus courte que la ligne droite entre les deux lieux ({fmt.km(f['straight'])}).")
    elif label == "speed_implausible" and f["speed"]:
        out.append(f"{fmt.km(f['distance'])} en {fmt.duration(f['duration'])}, soit {fmt.number(f['speed'])} km/h de moyenne,"
                   f" au-delà du maximum plausible en {mode} ({MAX_SPEED_KMH[f['mode']]} km/h).")
    elif label == "mode_mismatch":
        out.append(f"Trajet de {fmt.km(f['distance'])} en {mode} : ce mode n'est pas utilisé en dessous de {MIN_DISTANCE_KM[f['mode']]} km.")
    elif label == "distance_inflated":
        if f["road_factor"] is not None:
            out.append(f"Distance déclarée {fmt.km(f['distance'])} pour {fmt.km(f['straight'])} à vol d'oiseau"
                       f" (×{fmt.number(f['road_factor'], 1)}, une route fait rarement plus de ×{fmt.number(MAX_ROAD_FACTOR, 1)}).")
        else:
            out.append(f"Distance déclarée {fmt.km(f['distance'])} entre deux lieux distants de {fmt.km(f['straight'])} seulement.")
    else:
        if f["straight"] is not None and f["distance"] is not None:
            out.append(f"Distance déclarée {fmt.km(f['distance'])} cohérente avec la ligne droite ({fmt.km(f['straight'])}).")
        if f["speed"] is not None:
            out.append(f"Vitesse moyenne {fmt.number(f['speed'])} km/h, plausible en {mode}.")
        if not out:
            out.append("Dates, mode et distance cohérents.")
    return out


def report(t: dict[str, Any], label: str, confidence: float) -> str:
    """Analysis report given to the LLM to write its explanation."""
    f = facts(t)
    lines = [
        f"Transport du lot {t.get('lot')} en {fmt.mode(f['mode']).lower()}, de {t.get('origin')} à {t.get('destination')}.",
        f"Distance déclarée : {fmt.km(f['distance'])}. Ligne droite : {fmt.km(f['straight']) if f['straight'] is not None else 'inconnue'}.",
        f"Départ : {fmt.datetime_fr(t.get('departure_date'))}. Arrivée : {fmt.datetime_fr(t.get('arrival_date')) if t.get('arrival_date') else 'pas encore arrivé'}."
        + (f" Durée : {fmt.duration(f['duration'])}." if f["duration"] is not None and f["duration"] >= 0 else ""),
        f"Production du lot : {fmt.date(t.get('production_date'))}.",
        f"Verdict du modèle : {'ANOMALIE' if label != 'normal' else 'NORMAL'} ({LABEL_TEXT[label]}), confiance {round(confidence * 100)} %.",
        "Éléments : " + " ".join(reasons(t, label)),
    ]
    return "\n".join(lines)


LABEL_TEXT = {
    "normal": "aucune anomalie",
    "distance_inflated": "distance anormalement longue",
    "distance_too_short": "distance plus courte que la ligne droite",
    "speed_implausible": "vitesse impossible pour ce mode",
    "mode_mismatch": "mode de transport incohérent",
    "dates_inconsistent": "dates incohérentes",
    "missing_distance": "distance manquante",
}
