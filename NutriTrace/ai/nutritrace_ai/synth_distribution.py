"""Synthetic distributors, certificates and receptions in the format of the Laravel export.

As for transports, the demo database is too small to train on: these generators give
varied but realistic cases, including deliberately suspicious certificates and lots that
should be refused.
"""

import random
from datetime import datetime, timedelta
from typing import Any

from . import synth
from .certificates import NUMBER_FORMATS, RECOGNIZED, TYPES, normalize

SHOPS = ["Marché Vert", "Épicerie du Sahel", "Souk Bio", "Épicerie fine", "Supérette El Amen", "Les Halles", "Comptoir du Terroir", "Coopérative de consommateurs"]
# Shelf life in days, by category of product.
SHELF_LIFE = {"Fruits et légumes": (7, 45), "Produits laitiers": (10, 60), "Huiles et olives": (365, 720), "Céréales": (180, 540),
              "Miels": (365, 900), "Fruits secs et dattes": (120, 400), "Conserves et épicerie": (180, 730)}
FAKE_ISSUERS = ["Bio Sud Certification", "Certibio Méditerranée", "Green Label Africa", "Qualité Verte Internationale", "Organic Trust Tunisia",
                "Label Nature Plus", "Eco Garantie", "International Bio Council"]
ORGS = ["Domaine Chaâl", "Ferme El Baraka", "Maraîchers du Cap Bon", "Rucher du Zaghouan", "Vergers de Kairouan", "Huilerie Sidi Mansour",
        "Fromagerie de Béja", "Oasis de Tozeur", "Coopérative Ennour", "Domaine Errahma", "Conserverie du Cap Bon", "Moulin de Mateur"]
CERT_NAMES = {"BIO": ["Agriculture biologique"], "FAIR_TRADE": ["Commerce équitable"], "LOCAL": ["Produit local", "Produit du terroir local"],
              "SUSTAINABLE_AGRICULTURE": ["Agriculture durable", "Charte de production durable"], "OTHER": ["Sécurité des denrées alimentaires ISO 22000", "Produit du terroir"]}
TYPE_PHRASES = {"BIO": ["Agriculture biologique", "Mode de production biologique", "Produit issu de l'agriculture biologique"],
                "FAIR_TRADE": ["Commerce équitable", "Fair trade - commerce équitable"], "LOCAL": ["Produit local", "Indication d'origine locale - terroir"],
                "SUSTAINABLE_AGRICULTURE": ["Agriculture durable", "Charte de production durable"], "OTHER": ["Système de management de la sécurité des denrées", "Qualification terroir"]}
LOCAL_BODIES = ["Groupement interprofessionnel des fruits", "Groupement des fromagers de Béja", "Groupement des viticulteurs de Mornag", "Chambre d'agriculture de Nabeul", "APIA"]


def day(moment: datetime) -> str:
    return moment.strftime("%Y-%m-%d")


# --- distributors ---------------------------------------------------------------------------

def products(rng: random.Random) -> tuple[str, str, str]:
    name, category, unit, _, _, transformed = rng.choice(synth.PRODUCTS)
    if transformed and rng.random() < 0.4:
        name, unit = transformed[0], transformed[1]
        category = {"Huile d'olive extra vierge": "Huiles et olives", "Harissa traditionnelle": "Conserves et épicerie", "Couscous fin": "Céréales",
                    "Fromage frais": "Produits laitiers", "Concentré de tomate": "Conserves et épicerie"}.get(name, category)
    return name, category, unit


def distributor(rng: random.Random) -> dict[str, Any]:
    today = datetime(rng.choice([2025, 2026]), rng.randint(1, 12), rng.randint(1, 28), 0, 0)
    city = rng.choice(list(synth.PLACES))
    counter = rng.randint(1, 800)
    distributions, sales = [], []

    for _ in range(rng.randint(2, 16)):
        counter += rng.randint(1, 9)
        name, category, unit = products(rng)
        lot = f"LOT-{today.year}-{counter:03d}"
        amount = float(rng.choice([20, 40, 60, 100, 150, 200, 250, 300, 500, 800, 1000, 1200]) if unit != "t" else rng.randint(1, 8))
        status = rng.choices(["pending", "received", "in_store", "rejected", "cancelled"], weights=[10, 12, 68, 6, 4])[0]
        received = today - timedelta(days=rng.randint(0, 60), hours=rng.randint(0, 10))
        sent = received - timedelta(days=rng.randint(0, 2), hours=rng.randint(1, 8))
        low, high = SHELF_LIFE.get(category, (30, 365))
        produced = sent - timedelta(days=rng.randint(1, 20))
        expiration = None if rng.random() < 0.08 else produced + timedelta(days=rng.randint(low, high))
        if expiration and rng.random() < 0.25:
            # Push some lots close to their date to make the expiry questions interesting.
            expiration = today + timedelta(days=rng.randint(-6, 10))

        remaining = amount
        if status == "in_store":
            for _ in range(rng.randint(0, 9)):
                if remaining <= 0:
                    break
                sold = min(remaining, round(amount * rng.uniform(0.03, 0.3), 1 if unit != "t" else 2))
                if rng.random() < 0.12:
                    sold = remaining
                remaining = round(remaining - sold, 2)
                moment = received + timedelta(hours=rng.uniform(4, max(5.0, (today - received).total_seconds() / 3600)))
                sales.append({"lot": lot, "product": name, "unit": unit, "quantity": sold, "date": min(moment, today).isoformat()})

        distributions.append({
            "lot": lot, "product": name, "category": category, "unit": unit, "status": status,
            "status_label": {"pending": "En attente de réception", "received": "Reçu", "in_store": "En magasin", "rejected": "Refusé", "cancelled": "Annulé par l'expéditeur"}[status],
            "lot_status_label": "Épuisé" if status == "in_store" and remaining <= 0 else None,
            "quantity": amount, "remaining": remaining if status in ("received", "in_store") else amount,
            "production_date": day(produced), "expiration_date": day(expiration) if expiration else None,
            "sent_at": sent.isoformat(), "received_at": received.isoformat() if status in ("received", "in_store") else None,
            "sender": f"{rng.choice(synth.FARMS)} {rng.choice(synth.FARM_NAMES)}",
        })

    return {"today": day(today), "distributor": {"name": f"{rng.choice(SHOPS)} {city}", "city": city}, "distributions": distributions, "sales": sales}


# --- certificates ---------------------------------------------------------------------------

def number_for(issuer: str, year: int, rng: random.Random) -> str:
    key = normalize(issuer)
    if key == "ecocert":
        return f"TN-BIO-{year}-{rng.randint(1, 9999):04d}"
    if key == "ccpb":
        return f"CCPB-TN-{rng.randint(1000, 9999)}"
    if key == "fair for life":
        return f"FFL-{year}-{rng.randint(1000, 9999)}"
    if key == "innorpi":
        return rng.choice([f"INN-{year}-{rng.randint(100, 999)}", f"ISO22-TN-{rng.randint(1000, 9999)}"])
    if key == "office national de l'huile":
        return f"ONH-{year}-{rng.randint(100, 999)}"
    prefix = "".join(w[0] for w in issuer.split() if w[0].isalpha()).upper()[:4] or "CERT"
    return f"{prefix}-{year}-{rng.randint(10, 9999):03d}"


def lookalike(name: str, rng: random.Random) -> str:
    """'Ecocert' -> 'Ecocerts', 'Eco-cert', 'Ecocrt'..."""
    i = rng.randint(1, max(1, len(name) - 2))
    return rng.choice([name + "s", name[:i] + "-" + name[i:], name[:i] + name[i + 1:], name[:i] + name[i] + name[i:], name.replace("e", "é", 1)])


def document_text(c: dict[str, Any], rng: random.Random, **wrong) -> str:
    issued, expires = datetime.fromisoformat(c["issue_date"]), c["expiration_date"] and datetime.fromisoformat(c["expiration_date"])
    lines = [
        wrong.get("issuer", c["issuer"]).upper() if rng.random() < 0.5 else wrong.get("issuer", c["issuer"]),
        rng.choice(["CERTIFICAT DE CONFORMITÉ", "CERTIFICAT", "ATTESTATION DE CERTIFICATION"]),
        f"Certificat n° {wrong.get('number', c['number'])}" if c["number"] or "number" in wrong else "",
        f"Délivré à : {wrong.get('holder', c['owner']['organization'])}",
        f"Produit(s) couvert(s) : {c['target']['name']}" if rng.random() < 0.8 else "",
        f"Référentiel : {wrong.get('type', rng.choice(TYPE_PHRASES[c['type']]))}",
        f"Date de délivrance : {wrong.get('issued', issued.strftime('%d/%m/%Y'))}",
        f"Valable jusqu'au : {expires.strftime('%d/%m/%Y')}" if expires else "",
        rng.choice(["Ce certificat atteste que les produits désignés sont conformes au référentiel indiqué.",
                    "L'organisme certificateur atteste la conformité de l'opérateur au référentiel cité.",
                    "Document délivré après audit sur site."]),
    ]
    return "\n".join(line for line in lines if line)


def certificate(rng: random.Random, kind: str | None = None) -> dict[str, Any]:
    """A certificate, genuine or with one or two defects."""
    kind = kind or rng.choices(TYPES, weights=[40, 15, 15, 15, 15])[0]
    today = datetime(rng.choice([2025, 2026]), rng.randint(1, 12), rng.randint(1, 28))
    issued = today - timedelta(days=rng.randint(5, 700))
    years = rng.choice([1, 2, 2, 3]) if kind in ("BIO", "FAIR_TRADE") else rng.choice([1, 2, 3, 3, 4])
    expires = issued + timedelta(days=int(365 * years) - 1)
    issuer = rng.choice(RECOGNIZED[kind] + (LOCAL_BODIES if kind in ("LOCAL", "OTHER") else []))
    product, _, _ = products(rng)
    owner = rng.choice(ORGS)
    c = {
        "id": rng.randint(1, 10_000), "name": rng.choice(CERT_NAMES[kind]), "type": kind, "issuer": issuer,
        "number": number_for(issuer, issued.year, rng), "issue_date": day(issued), "expiration_date": day(expires), "today": day(today),
        "status": "PENDING", "owner": {"name": owner, "organization": owner, "verified": rng.random() < 0.6},
        "target": {"kind": rng.choice(["produit", "lot"]), "name": product,
                   "production_methods": [rng.choice(["biologique", "biologique", "raisonnée"]) if kind == "BIO" else rng.choice(["conventionnelle", "raisonnée", "biologique"])]},
        "duplicates": 0,
    }
    wrong: dict[str, str] = {}
    defects = rng.choices(["none", "lookalike", "fake", "other_type", "no_number", "bad_format", "year", "duplicate", "long", "no_expiry",
                           "expired", "no_doc", "unreadable", "doc_number", "doc_issuer", "doc_holder", "doc_type", "doc_dates", "conventional"],
                          weights=[34, 7, 8, 3, 4, 4, 3, 5, 3, 3, 3, 5, 3, 6, 3, 3, 2, 3, 4], k=rng.choice([1, 1, 2, 2, 3]))
    for defect in defects:
        if defect == "lookalike":
            c["issuer"] = lookalike(rng.choice(RECOGNIZED[kind]), rng)
        elif defect == "fake":
            c["issuer"] = rng.choice(FAKE_ISSUERS)
            c["number"] = number_for(c["issuer"], issued.year, rng)
        elif defect == "other_type":
            others = [n for t, names in RECOGNIZED.items() if t != kind for n in names if n not in RECOGNIZED[kind]]
            c["issuer"] = rng.choice(others)
        elif defect == "no_number":
            c["number"] = None
        elif defect == "bad_format" and normalize(c["issuer"]) in NUMBER_FORMATS:
            c["number"] = rng.choice([f"{rng.randint(1000, 99999)}", f"BIO-{rng.randint(100, 999)}", "0000"])
        elif defect == "year" and c["number"]:
            c["number"] = c["number"].replace(str(issued.year), str(issued.year - rng.randint(2, 6)))
        elif defect == "duplicate":
            c["duplicates"] = rng.randint(1, 3)
        elif defect == "long":
            c["expiration_date"] = day(issued + timedelta(days=365 * rng.randint(6, 10)))
        elif defect == "no_expiry":
            c["expiration_date"] = None
        elif defect == "expired":
            c["expiration_date"] = day(today - timedelta(days=rng.randint(1, 200)))
        elif defect == "doc_number":
            wrong["number"] = number_for(c["issuer"], issued.year, rng)
        elif defect == "doc_issuer":
            wrong["issuer"] = rng.choice(FAKE_ISSUERS + ["Organisme de contrôle"])
        elif defect == "doc_holder":
            wrong["holder"] = rng.choice([o for o in ORGS if o != owner])
        elif defect == "doc_type" and kind != "OTHER":
            wrong["type"] = rng.choice([p for t, ps in TYPE_PHRASES.items() if t not in (kind, "OTHER") for p in ps
                                        if not any(w in normalize(p) for w in ["biolog", "bio", "equitable", "local", "terroir", "durable"])] or ["Qualité supérieure"])
        elif defect == "doc_dates":
            wrong["issued"] = (issued - timedelta(days=rng.randint(40, 400))).strftime("%d/%m/%Y")
        elif defect == "conventional" and kind == "BIO":
            c["target"]["production_methods"] = ["conventionnelle"]

    if "no_doc" in defects:
        c["document"] = {"present": False}
    elif "unreadable" in defects:
        c["document"] = {"present": True, "mime": rng.choice(["image/jpeg", "application/pdf"]), "text": ""}
    else:
        c["document"] = {"present": True, "mime": "application/pdf", "text": document_text(c, rng, **wrong)}
    return c


# --- receptions -----------------------------------------------------------------------------

WARNING_POOL = [
    ("high", "bio_unverified", "Mention « bio » sans certificat biologique vérifié."),
    ("medium", "certificate_pending", "Certificat en cours de vérification : il n'est pas encore pris en compte."),
    ("low", "unverified_actors", "Acteur(s) dont l'organisation n'a pas été vérifiée."),
    ("medium", "environmental_data_missing", "Aucune donnée environnementale chiffrée : l'empreinte ne peut pas être évaluée."),
    ("high", "local_claim_far", "Mention « local » alors que le produit a parcouru 420 km."),
    ("high", "certificate_without_proof", "Certificat vérifié mais sans numéro ou document consultable."),
    ("medium", "transport_without_distance", "Transport sans distance renseignée."),
]


def reception(rng: random.Random) -> dict[str, Any]:
    name, category, unit = products(rng)
    low, high = SHELF_LIFE.get(category, (30, 365))
    produced = datetime(rng.choice([2025, 2026]), rng.randint(1, 12), rng.randint(1, 28))
    shelf = rng.randint(low, high)
    elapsed = int(shelf * rng.choice([rng.uniform(0.02, 0.4), rng.uniform(0.4, 0.8), rng.uniform(0.75, 1.15)]))
    today = produced + timedelta(days=max(1, elapsed))
    amount = float(rng.choice([50, 100, 200, 300, 500, 1000]))

    certs = []
    for _ in range(rng.choices([0, 1, 2], weights=[35, 45, 20])[0]):
        status = rng.choices(["VERIFIED", "PENDING", "REJECTED", "EXPIRED"], weights=[60, 20, 10, 10])[0]
        certs.append({"name": rng.choice(["Agriculture biologique", "Commerce équitable", "Produit local", "Agriculture durable"]),
                      "type": rng.choice(TYPES), "status": status, "valid": status == "VERIFIED", "expiration_date": None})
    warnings = [{"severity": s, "code": code, "message": m} for s, code, m in rng.sample(WARNING_POOL, rng.choices([0, 1, 2], weights=[50, 35, 15])[0])]

    transport = rng.choice(synth.normal_transports(rng, 1) + [None])
    if transport and rng.random() < 0.18:
        transport = synth.inject(rng, transport, rng.choice(["distance_inflated", "speed_implausible", "mode_mismatch", "dates_inconsistent", "missing_distance"]))
    has_expiry = rng.random() > 0.07
    return {
        "today": day(today),
        "lot": {"number": f"LOT-{produced.year}-{rng.randint(1, 999):03d}", "product": name, "category": category, "unit": unit,
                "quantity": amount, "production_date": day(produced), "expiration_date": day(produced + timedelta(days=shelf)) if has_expiry else None},
        "distribution": {"quantity": amount, "sender": f"{rng.choice(synth.FARMS)} {rng.choice(synth.FARM_NAMES)}", "sent_at": day(today)},
        "certifications": certs,
        "warnings": warnings,
        "chain_valid": rng.random() > 0.06,
        "under_investigation": rng.random() < 0.06,
        "transparency_score": rng.randint(15, 98),
        "transport": transport,
    }
