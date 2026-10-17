"""Check of a lot a distributor is about to receive: features, reference rules, reasons.

A reception is the payload of App\\Services\\Ai\\DistributionAiContext::forReception: the
lot (dates, quantity), its certifications, the warnings NutriTrace computed, the state
of its traceability journal and the transport that brings it.
"""

from typing import Any

from . import formatting as fmt
from .features import LABEL_TEXT as TRANSPORT_LABELS
from .features import rule_label as transport_rule_label

DECISIONS = ["accepter", "reserves", "refuser"]
DECISION_TEXT = {"accepter": "accepter", "reserves": "accepter avec réserves", "refuser": "refuser"}

COVERED = {"bio_unverified", "chain_broken", "under_investigation", "local_claim_far",
           "certificate_rejected", "certificate_expired", "certificate_pending"}

FEATURE_NAMES = [
    "has_expiration", "days_left", "shelf_ratio", "expired",
    "valid_certs", "pending_certs", "rejected_certs", "expired_certs",
    "bio_unverified", "local_claim_far", "warnings_high", "warnings_medium",
    "chain_valid", "under_investigation", "transparency",
    "transport_known", "transport_dates", "transport_other_anomaly",
]


def facts(r: dict[str, Any], transport_label: str | None = None) -> dict[str, Any]:
    lot = r.get("lot") or {}
    today = fmt.parse_date(r.get("today"))
    produced, expires = fmt.parse_date(lot.get("production_date")), fmt.parse_date(lot.get("expiration_date"))
    days_left = (expires.date() - today.date()).days if expires and today else None
    shelf = (expires - produced).days if expires and produced else None
    certs = r.get("certifications") or []
    codes = [w.get("code") for w in r.get("warnings") or []]
    transport = r.get("transport")
    if transport and transport_label is None:
        transport_label = transport_rule_label(transport)

    return {
        "days_left": days_left,
        "shelf_ratio": max(0.0, days_left / shelf) if days_left is not None and shelf else None,
        "expired": days_left is not None and days_left < 0,
        "valid": [c for c in certs if c.get("valid")],
        "pending": [c for c in certs if c.get("status") == "PENDING"],
        "rejected": [c for c in certs if c.get("status") == "REJECTED"],
        "expired_certs": [c for c in certs if c.get("status") == "EXPIRED" or (c.get("status") == "VERIFIED" and not c.get("valid"))],
        "bio_unverified": "bio_unverified" in codes,
        "local_claim_far": "local_claim_far" in codes,
        # Warnings not already covered by a dedicated check below.
        "high": [w for w in r.get("warnings") or [] if w.get("severity") == "high" and w.get("code") not in COVERED],
        "medium": [w for w in r.get("warnings") or [] if w.get("severity") == "medium"],
        "chain_valid": bool(r.get("chain_valid", True)),
        "under_investigation": bool(r.get("under_investigation")),
        "transparency": r.get("transparency_score"),
        "transport": transport_label,
    }


def vector(r: dict[str, Any], transport_label: str | None = None) -> list[float]:
    f = facts(r, transport_label)
    return [
        float(f["days_left"] is not None), -1.0 if f["days_left"] is None else float(f["days_left"]),
        -1.0 if f["shelf_ratio"] is None else f["shelf_ratio"], float(f["expired"]),
        float(len(f["valid"])), float(len(f["pending"])), float(len(f["rejected"])), float(len(f["expired_certs"])),
        float(f["bio_unverified"]), float(f["local_claim_far"]), float(len(f["high"])), float(len(f["medium"])),
        float(f["chain_valid"]), float(f["under_investigation"]), -1.0 if f["transparency"] is None else float(f["transparency"]),
        float(f["transport"] is not None), float(f["transport"] == "dates_inconsistent"),
        float(f["transport"] not in (None, "normal", "dates_inconsistent")),
    ]


def findings(r: dict[str, Any], transport_label: str | None = None) -> tuple[list[str], list[str], list[str]]:
    """(reasons to refuse, reservations, positive points), in French."""
    f = facts(r, transport_label)
    lot = r.get("lot") or {}
    refuse, reserve, good = [], [], []

    if f["expired"]:
        refuse.append(f"La date limite du lot ({fmt.date(lot.get('expiration_date'))}) est dépassée depuis {-f['days_left']} jour(s).")
    elif f["days_left"] is not None and f["days_left"] <= 1:
        refuse.append(f"Le lot expire {'aujourd’hui' if f['days_left'] == 0 else 'demain'} : il ne pourra pas être vendu.")
    elif f["shelf_ratio"] is not None and f["shelf_ratio"] < 0.08:
        refuse.append(f"Il ne reste que {f['days_left']} jours avant la date limite, soit moins de 8 % de la durée de conservation.")
    elif f["days_left"] is not None and (f["days_left"] < 7 or (f["shelf_ratio"] is not None and f["shelf_ratio"] < 0.3)):
        reserve.append(f"Date limite proche : {f['days_left']} jours restants ({round((f['shelf_ratio'] or 0) * 100)} % de la durée de conservation).")
    elif f["days_left"] is not None:
        good.append(f"Date limite dans {f['days_left']} jours ({round((f['shelf_ratio'] or 0) * 100)} % de la durée de conservation restante).")
    else:
        reserve.append("Aucune date limite renseignée : vérifiez la conservation à la réception.")

    if not f["chain_valid"]:
        refuse.append("Le journal de traçabilité a été modifié après coup : son intégrité n'est plus garantie.")
    else:
        good.append("Journal de traçabilité intègre.")
    if f["under_investigation"]:
        reserve.append("Un signalement concernant ce lot est en cours d'examen.")
    if f["bio_unverified"]:
        reserve.append("Le lot est présenté comme bio sans certificat biologique vérifié : ne l'affichez pas comme bio.")
    if f["local_claim_far"]:
        reserve.append("La mention « local » ne correspond pas à la distance parcourue.")
    for c in f["rejected"]:
        reserve.append(f"Le certificat « {c.get('name')} » a été refusé : il ne doit pas être mis en avant.")
    for c in f["pending"]:
        reserve.append(f"Le certificat « {c.get('name')} » est encore en cours de vérification.")
    if f["valid"]:
        good.append(f"{len(f['valid'])} certification(s) valide(s) : {', '.join(c.get('name', '') for c in f['valid'])}.")
    for w in f["high"]:
        reserve.append(w.get("message", "").rstrip(".") + ".")
    if f["transparency"] is not None and f["transparency"] < 40:
        reserve.append(f"Score de transparence faible ({fmt.number(f['transparency'])}/100).")

    if f["transport"] == "dates_inconsistent":
        reserve.append("Les dates du transport sont incohérentes : contrôlez la chronologie du lot.")
    elif f["transport"] not in (None, "normal"):
        reserve.append(f"Anomalie détectée sur le transport : {TRANSPORT_LABELS.get(f['transport'], f['transport'])}.")
    elif f["transport"] == "normal":
        good.append("Transport cohérent (distance, mode et durée).")
    return refuse, reserve, good


def rule_decision(r: dict[str, Any], transport_label: str | None = None) -> str:
    refuse, reserve, _ = findings(r, transport_label)
    return "refuser" if refuse else "reserves" if reserve else "accepter"


def reasons(r: dict[str, Any], decision: str, transport_label: str | None = None) -> list[str]:
    refuse, reserve, good = findings(r, transport_label)
    if decision == "refuser":
        return refuse + reserve
    if decision == "reserves":
        return reserve + good[:1]
    return good


def report(r: dict[str, Any], decision: str, confidence: float, transport_label: str | None = None) -> str:
    lot = r.get("lot") or {}
    dist = r.get("distribution") or {}
    lines = [
        f"Lot {lot.get('number')} : {lot.get('product')}, {fmt.quantity(dist.get('quantity') or lot.get('quantity'), lot.get('unit', ''))},"
        f" envoyé par {dist.get('sender') or '?'}.",
        f"Production : {fmt.date(lot.get('production_date'))}. Date limite : {fmt.date(lot.get('expiration_date')) if lot.get('expiration_date') else 'non renseignée'}."
        f" Aujourd'hui : {fmt.date(r.get('today'))}.",
        f"Verdict du modèle : {DECISION_TEXT[decision].upper()} (confiance {round(confidence * 100)} %).",
        "Éléments : " + " ".join(reasons(r, decision, transport_label)),
    ]
    return "\n".join(lines)


INTROS = {
    "accepter": ["Vous pouvez accepter ce lot.", "Rien ne s'oppose à la réception de ce lot."],
    "reserves": ["Vous pouvez accepter ce lot, avec quelques précautions.", "Ce lot peut être reçu, mais certains points demandent votre attention."],
    "refuser": ["Il est conseillé de refuser ce lot.", "Ce lot ne devrait pas être accepté en l'état."],
}
ADVICE = {
    "accepter": ["Confirmez la réception puis mettez-le en rayon.", "Il peut être mis en rayon normalement."],
    "reserves": ["Notez ces réserves et adaptez l'affichage en rayon.", "Acceptez-le en tenant compte de ces points, ou contactez l'expéditeur."],
    "refuser": ["Indiquez ce motif à l'expéditeur lors du refus.", "Refusez la réception en précisant ce motif : le lot retournera à son expéditeur."],
}


def explain(r: dict[str, Any], decision: str, rng=None, transport_label: str | None = None) -> str:
    import random

    rng = rng or random.Random(0)
    return " ".join([rng.choice(INTROS[decision]), *reasons(r, decision, transport_label)[:4], rng.choice(ADVICE[decision])])
