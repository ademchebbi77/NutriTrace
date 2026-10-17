"""Turns a lot's data (App\\Services\\Ai\\AiContext::forLot) into the text given to the LLM.

The same function renders the training prompts and the live prompts, so the model
always reads the data in the format it was trained on. Totals are computed here
rather than left to the model: small models are bad at arithmetic.
"""

from collections import Counter
from dataclasses import dataclass, field
from typing import Any

from . import formatting as fmt

# Longest journeys are cut to their most recent events to keep the prompt short.
MAX_EVENTS = 25


@dataclass
class Summary:
    transports: list[dict[str, Any]]
    active: list[dict[str, Any]]
    total_km: float
    missing_distance: int
    modes: Counter
    transport_co2: float | None
    productions: list[dict[str, Any]] = field(default_factory=list)
    last_event: dict[str, Any] | None = None
    longest: dict[str, Any] | None = None


def summarize(ctx: dict[str, Any]) -> Summary:
    transports = ctx.get("transports") or []
    active = [t for t in transports if t.get("status") != "cancelled"]
    with_distance = [t for t in active if t.get("distance_km") is not None]
    co2 = [t["co2_kg"] for t in active if t.get("co2_kg") is not None]
    journey = ctx.get("journey") or []

    return Summary(
        transports=transports,
        active=active,
        total_km=round(sum(t["distance_km"] for t in with_distance), 1),
        missing_distance=len(active) - len(with_distance),
        modes=Counter(fmt.mode(t["mode"]).lower() for t in active),
        transport_co2=round(sum(co2), 2) if co2 else None,
        productions=[e for e in journey if e.get("type") == "PRODUCTION"],
        last_event=journey[-1] if journey else None,
        longest=max(with_distance, key=lambda t: t["distance_km"]) if with_distance else None,
    )


def describe_transport(t: dict[str, Any]) -> str:
    parts = [
        f"lot {t['lot']}",
        f"{fmt.mode(t['mode'])}",
        f"de {t['origin']} à {t['destination']}",
        fmt.km(t.get("distance_km")),
        f"départ {fmt.datetime_fr(t.get('departure_date'))}",
        f"arrivée {fmt.datetime_fr(t['arrival_date'])}" if t.get("arrival_date") else "pas encore arrivé",
        f"statut {t.get('status_label', t.get('status'))}",
    ]
    if t.get("quantity") is not None:
        parts.append(f"quantité {fmt.quantity(t['quantity'], t.get('unit', ''))}")
    if t.get("co2_kg") is not None:
        parts.append(f"CO₂ {fmt.kg(t['co2_kg'])}")
    return " ; ".join(parts)


def render(ctx: dict[str, Any]) -> str:
    lot, product = ctx["lot"], ctx["product"]
    s = summarize(ctx)
    lines: list[str] = []

    lines.append(f"LOT {lot['number']} : {product['name']} (catégorie {product.get('category') or 'inconnue'}"
                 f"{', origine déclarée ' + product['origin'] if product.get('origin') else ''}).")
    lines.append(f"Statut : {lot.get('status_label')}. Quantité restante : {fmt.quantity(lot.get('quantity'), lot['unit'])}"
                 f" sur {fmt.quantity(lot.get('initial_quantity'), lot['unit'])}. Date de production : {fmt.date(lot.get('production_date'))}."
                 f" Date limite : {fmt.date(lot['expiration_date']) if lot.get('expiration_date') else 'non renseignée'}.")
    if ctx.get("source_lots"):
        lines.append(f"Lot transformé, fabriqué à partir des lots : {', '.join(ctx['source_lots'])}.")

    journey = ctx.get("journey") or []
    shown = journey[-MAX_EVENTS:]
    lines.append(f"\nPARCOURS ({len(journey)} étapes{', les ' + str(MAX_EVENTS) + ' dernières affichées' if len(journey) > MAX_EVENTS else ''}) :")
    for i, event in enumerate(shown, start=len(journey) - len(shown) + 1):
        where = f" - {event['location']}" if event.get("location") else ""
        who = f" - {event['actor']}" if event.get("actor") else ""
        lines.append(f"{i}. {fmt.datetime_fr(event['occurred_at'])} [{event['type']}] {event['description']}{where}{who} (lot {event['lot']})")

    lines.append(f"\nTRANSPORTS ({len(s.transports)}) :")
    if not s.transports:
        lines.append("Aucun transport enregistré.")
    for i, t in enumerate(s.transports, start=1):
        lines.append(f"{i}. {describe_transport(t)}")
    modes = ", ".join(f"{m} ({n})" for m, n in s.modes.most_common()) or "aucun"
    lines.append(f"Distance totale parcourue (hors transports annulés) : {fmt.km(s.total_km)}"
                 f"{f' ({s.missing_distance} transport(s) sans distance)' if s.missing_distance else ''}. Modes utilisés : {modes}."
                 f" CO₂ total des transports : {fmt.kg(s.transport_co2) if s.transport_co2 is not None else 'inconnu'}.")

    impact = ctx.get("environmental_impact")
    if impact:
        lines.append(f"\nEMPREINTE ENVIRONNEMENTALE : note {impact.get('grade') or '?'} (score {fmt.number(impact.get('score'))}/100),"
                     f" {fmt.number(impact['co2_per_kg'], 2) + ' kg CO₂e par kg' if impact.get('co2_per_kg') is not None else 'CO₂ par kg inconnu'},"
                     f" food miles {fmt.km(impact.get('food_miles_km'))}.")
    else:
        lines.append("\nEMPREINTE ENVIRONNEMENTALE : non évaluée.")

    local = ctx.get("local") or {}
    if local.get("is_local") is None:
        lines.append("PRODUIT LOCAL : impossible à déterminer (lieux non géolocalisés ou lot pas encore arrivé).")
    else:
        lines.append(f"PRODUIT LOCAL : {'oui' if local['is_local'] else 'non'} (origine à {fmt.km(local.get('distance_km'))}"
                     f" du point d'arrivée, seuil {fmt.km(local.get('radius_km'))}).")

    certifications = ctx.get("certifications") or []
    if certifications:
        lines.append("CERTIFICATIONS : " + " ; ".join(
            f"{c['name']} ({c.get('issuing_organization') or 'organisme inconnu'}, {'valide' if c.get('valid') else 'non valide'}"
            f"{', expire le ' + fmt.date(c['expiration_date']) if c.get('expiration_date') else ''})" for c in certifications) + ".")
    else:
        lines.append("CERTIFICATIONS : aucune.")

    transparency = ctx.get("transparency") or {}
    lines.append(f"TRANSPARENCE : {fmt.number(transparency.get('score'))}/100 ({transparency.get('level', 'inconnue')}).")
    chain = ctx.get("chain") or {}
    lines.append(f"JOURNAL DE TRAÇABILITÉ : {'intègre' if chain.get('valid') else 'ROMPU (modifié après coup)'}, {chain.get('events', 0)} événements chaînés par empreinte SHA-256.")

    warnings = ctx.get("warnings") or []
    lines.append("POINTS DE VIGILANCE : " + (" ; ".join(w["message"].rstrip(".") for w in warnings) if warnings else "aucun") + ".")
    lines.append(f"SIGNALEMENT EN COURS D'EXAMEN : {'oui' if ctx.get('under_investigation') else 'non'}.")

    return "\n".join(lines)
