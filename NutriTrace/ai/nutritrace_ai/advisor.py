"""Improvement advice for a lot's environmental grade.

The payload (App\\Services\\Ai\\ImpactAiContext::forAdvice) carries the lot's figures per kg,
the stages of its CO₂, and the scoring settings of config/footprint.php, so the score is
recomputed here exactly as App\\Services\\Scoring\\FootprintCalculator::score() does, and each
lever is quantified: how much it would raise the score, and what it takes to reach the next grade.
"""

import random
from typing import Any

from . import formatting as fmt

INDICATORS = {
    "co2": ("co2_per_kg", "le CO₂", "kg CO₂e par kg"),
    "water": ("water_per_kg", "l'eau", "L par kg"),
    "energy": ("energy_per_kg", "l'énergie", "kWh par kg"),
    "food_miles": ("food_miles", "la distance parcourue", "km"),
}
STAGES = {"production_co2": "la production", "transformation_co2": "la transformation", "transport_co2": "les transports", "packaging_co2": "l'emballage"}
# Lower-emission mode for each mode, with the factors of config/footprint.php.
MODE_SWITCH = {"van": "truck", "truck": "train", "plane": "ship"}
MODE_TEXT = {"truck": "camion", "van": "camionnette", "train": "train", "ship": "bateau", "plane": "avion"}


def sub_score(value: float, best: float, worst: float) -> float:
    if worst <= best:
        return 100.0 if value <= best else 0.0
    return max(0.0, min(100.0, (worst - value) / (worst - best) * 100))


def analyse(a: dict[str, Any]) -> dict[str, Any]:
    config = a["config"]
    per_kg = a.get("per_kg") or {}
    known = {key: per_kg.get(key) for key in INDICATORS if per_kg.get(key) is not None and (key != "food_miles" or per_kg[key] > 0)}
    weights = {key: config["weights"][key] for key in known}
    total_weight = sum(weights.values()) or 1.0
    subs = {key: sub_score(value, *(config["thresholds"][INDICATORS[key][0]][k] for k in ("best", "worst"))) for key, value in known.items()}
    score = sum(subs[k] * weights[k] for k in known) / total_weight if known else None

    grades = sorted(config["grades"].items(), key=lambda item: -item[1])  # A 80, B 60...
    grade = next((g for g, minimum in grades if score is not None and round(score) >= minimum), "E") if score is not None else None
    better = [(g, minimum) for g, minimum in grades if score is not None and minimum > round(score)]
    target = better[-1] if better else None

    levers = []
    for key, value in known.items():
        share = weights[key] / total_weight
        best, worst = (config["thresholds"][INDICATORS[key][0]][k] for k in ("best", "worst"))
        gain = share * (100 - subs[key])
        lever = {"indicator": key, "value": value, "gain": round(gain, 1), "best": best}
        if target and gain > 0:
            needed = (target[1] - score) / share
            if needed <= 100 - subs[key] + 1e-9:
                lever["target_value"] = round(worst - (subs[key] + needed) / 100 * (worst - best), 3)
        levers.append(lever)
    levers.sort(key=lambda lever: -lever["gain"])

    stages = {k: v for k, v in (a.get("stages") or {}).items() if v}
    total_co2 = sum(stages.values())
    shares = {k: v / total_co2 for k, v in stages.items()} if total_co2 > 0 else {}

    return {"score": None if score is None else round(score), "grade": grade, "target": target[0] if target else None,
            "levers": levers, "shares": shares, "missing": a.get("missing") or []}


def advice(a: dict[str, Any]) -> list[str]:
    """Concrete levers in French, the most effective first."""
    r = analyse(a)
    out: list[str] = []
    if r["score"] is None:
        return ["Aucune donnée chiffrée sur le CO₂, l'eau ou l'énergie : déclarez au moins une de ces valeurs pour que le lot reçoive une note."]

    for lever in r["levers"]:
        if lever["gain"] < 1:
            continue
        _, name, unit = INDICATORS[lever["indicator"]]
        current = f"{fmt.smart(lever['value'])} {unit}"
        if "target_value" in lever:
            goal = f"{fmt.smart(lever['target_value'])} {unit}"
            out.append(f"Réduire {name} de {current} à {goal} suffirait pour passer en note {r['target']}.")
        else:
            out.append(f"Réduire {name} (actuellement {current}) peut rapporter jusqu'à {fmt.number(lever['gain'])} points de score.")
        if len(out) == 2:
            break

    if r["shares"]:
        stage, share = max(r["shares"].items(), key=lambda item: item[1])
        out.append(f"{STAGES.get(stage, stage).capitalize()} représente {fmt.number(share * 100)} % du CO₂ du lot : c'est le premier poste à travailler.")
        if stage == "transport_co2" and share >= 0.3:
            modes = [t.get("mode") for t in a.get("transports") or [] if t.get("mode") in MODE_SWITCH]
            if modes:
                mode = max(set(modes), key=modes.count)
                factors = a["config"].get("factors") or {}
                cut = 1 - factors.get(MODE_SWITCH[mode], 0) / factors[mode] if factors.get(mode) else None
                out.append(f"Remplacer le {MODE_TEXT[mode]} par le {MODE_TEXT[MODE_SWITCH[mode]]} réduirait le CO₂ du transport"
                           + (f" d'environ {fmt.number(cut * 100)} %." if cut else "."))

    names = {"water_l": "l'eau", "energy_kwh": "l'énergie", "co2_kg": "le CO₂", "waste_kg": "les déchets", "packaging_co2_kg": "l'emballage"}
    missing = [names[m] for m in r["missing"] if m in names]
    if missing:
        out.append(f"Déclarer {', '.join(missing)} compléterait l'empreinte et améliorerait le score de transparence du lot.")
    if not out:
        out.append(f"Le lot est déjà en note {r['grade']} : maintenez ces pratiques et continuez à déclarer vos données.")
    return out


def report(a: dict[str, Any]) -> str:
    r = analyse(a)
    lines = [
        f"Lot {a.get('lot')} ({a.get('product')}, catégorie {a.get('category')}).",
        f"Note actuelle : {r['grade'] or 'aucune'} (score {r['score'] if r['score'] is not None else '?'}/100)."
        + (f" Note suivante : {r['target']}." if r["target"] else ""),
        "Leviers : " + " ".join(advice(a)),
    ]
    return "\n".join(lines)


INTROS = ["Voici comment améliorer la note environnementale de ce lot.", "Plusieurs leviers peuvent améliorer l'empreinte de ce lot."]
CLOSINGS = ["Commencez par le premier levier : c'est celui qui rapporte le plus de points.", "Chaque donnée déclarée et mesurée renforce aussi la confiance des consommateurs."]


def explain(a: dict[str, Any], rng: random.Random | None = None) -> str:
    rng = rng or random.Random(0)
    r = analyse(a)
    if r["score"] is None:
        return advice(a)[0]
    intro = f"Ce lot a la note {r['grade']} ({r['score']}/100)." if r["grade"] in ("A",) else rng.choice(INTROS) + f" Il a aujourd'hui la note {r['grade']} ({r['score']}/100)."
    return " ".join([intro, *advice(a)[:3], rng.choice(CLOSINGS)])
