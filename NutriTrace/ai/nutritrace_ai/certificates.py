"""Risk of a certificate submitted to NutriTrace: features, reference rules, reasons.

A certificate is the payload of App\\Services\\Ai\\DistributionAiContext::forCertification:
the form fields, the owner, what it covers, how many other certificates use the same
number, and the proof document (PDF, base64).

The rules give the labels of the synthetic training set (faible / moyen / eleve); the
random forest trained on them is what the service uses.
"""

import re
import unicodedata
from datetime import date
from difflib import SequenceMatcher
from typing import Any

from . import formatting as fmt
from .pdf_text import extract

TYPES = ["BIO", "LOCAL", "FAIR_TRADE", "SUSTAINABLE_AGRICULTURE", "OTHER"]
TYPE_LABELS = {
    "BIO": "agriculture biologique", "LOCAL": "produit local", "FAIR_TRADE": "commerce équitable",
    "SUSTAINABLE_AGRICULTURE": "agriculture durable", "OTHER": "autre certification",
}

# Bodies recognised for each kind of label (simplified registry for the project).
RECOGNIZED = {
    "BIO": ["Ecocert", "CCPB", "Bureau Veritas", "Control Union", "Ceres", "Suolo e Salute", "Certipaq", "ICEA"],
    "FAIR_TRADE": ["Fair for Life", "Fairtrade", "Max Havelaar", "FLOCERT", "Ecocert"],
    "SUSTAINABLE_AGRICULTURE": ["INNORPI", "Office National de l'Huile", "GlobalG.A.P.", "Rainforest Alliance", "Bureau Veritas", "CTAB"],
    "LOCAL": ["APIA", "Groupement interprofessionnel des fruits", "Groupement interprofessionnel des dattes", "Office National de l'Huile"],
    "OTHER": ["INNORPI", "Bureau Veritas", "SGS", "TÜV", "AFNOR"],
}
# Local and terroir labels are granted by professional bodies: recognised by their form.
INSTITUTION = re.compile(r"^(groupement|chambre|office|union|commissariat|agence|apia|onagri|gda)\b", re.I)
INSTITUTION_TYPES = {"LOCAL", "OTHER"}

# Number formats of the bodies that publish one.
NUMBER_FORMATS = {
    "ecocert": r"^TN-BIO-\d{4}-\d{4}$",
    "ccpb": r"^CCPB-TN-\d{4}$",
    "fair for life": r"^FFL-\d{4}-\d{4}$",
    "innorpi": r"^(INN-\d{4}-\d{3}|ISO22-TN-\d{4})$",
    "office national de l'huile": r"^ONH-\d{4}-\d{3}$",
}
TYPE_WORDS = {
    "BIO": ["biologique", "bio", "organic"],
    "FAIR_TRADE": ["equitable", "fair"],
    "LOCAL": ["local", "terroir", "origine"],
    "SUSTAINABLE_AGRICULTURE": ["durable", "charte", "sustainable"],
}
MAX_YEARS = {"BIO": 3, "FAIR_TRADE": 3}

LEVELS = ["faible", "moyen", "eleve"]
LEVEL_TEXT = {"faible": "risque faible", "moyen": "risque moyen", "eleve": "risque élevé"}

FEATURE_NAMES = [f"type_{t}" for t in TYPES] + [
    "issuer_recognized", "issuer_recognized_other_type", "issuer_lookalike", "issuer_similarity",
    "has_number", "number_format_known", "number_format_ok", "number_year_gap",
    "duplicates", "validity_years", "no_expiration", "expired", "issued_in_future",
    "has_document", "document_readable", "doc_number", "doc_issuer", "doc_holder", "doc_type", "doc_dates", "doc_target",
    "bio_on_conventional",
]


def normalize(text: str | None) -> str:
    text = unicodedata.normalize("NFD", (text or "").lower())
    text = "".join(c for c in text if unicodedata.category(c) != "Mn").replace("’", "'")
    return re.sub(r"\s+", " ", text).strip()


def compact(text: str | None) -> str:
    """Letters and digits only: numbers are compared without spaces, dashes or slashes."""
    return re.sub(r"[^a-z0-9]", "", normalize(text))


def issuer_match(issuer: str, kind: str) -> dict[str, Any]:
    name = normalize(issuer)
    all_names = {normalize(n): n for names in RECOGNIZED.values() for n in names}
    for_type = {normalize(n) for n in RECOGNIZED.get(kind, [])}
    best, closest = 0.0, None
    for candidate, original in all_names.items():
        ratio = SequenceMatcher(None, name, candidate).ratio()
        if ratio > best:
            best, closest = ratio, original
    recognized = name in for_type or (kind in INSTITUTION_TYPES and bool(INSTITUTION.match(name)))
    return {
        "recognized": recognized,
        "other_type": not recognized and name in all_names,
        # Close to a known body without being it: "Ecocerts", "Eco-Cert", "Bureau Veritass".
        "lookalike": not recognized and name not in all_names and best >= 0.8,
        "similarity": best,
        "closest": closest,
    }


def facts(c: dict[str, Any]) -> dict[str, Any]:
    today = fmt.parse_date(c.get("today")) or None
    today = today.date() if today else date.today()
    issued = fmt.parse_date(c.get("issue_date"))
    expires = fmt.parse_date(c.get("expiration_date"))
    kind = c.get("type") or "OTHER"
    issuer = issuer_match(c.get("issuer") or "", kind)
    number = (c.get("number") or "").strip()

    pattern = next((p for body, p in NUMBER_FORMATS.items() if normalize(c.get("issuer")) == body), None)
    years = [int(y) for y in re.findall(r"(?<!\d)(20\d{2})(?!\d)", number)]
    text = extract(c.get("document"))
    doc = compact(text) if text else ""
    plain = normalize(text) if text else ""

    def found(value: str | None) -> bool | None:
        return None if not text or not value else compact(value) in doc

    dates_found = None
    if text and issued:
        forms = [issued.strftime("%d/%m/%Y"), issued.strftime("%Y-%m-%d")]
        dates_found = any(f in (text or "") for f in forms)

    return {
        "kind": kind,
        "issuer": issuer,
        "number": number,
        "number_format_known": pattern is not None,
        "number_format_ok": bool(re.match(pattern, number)) if pattern and number else None,
        "number_year_gap": abs(years[0] - issued.year) if years and issued else None,
        "duplicates": int(c.get("duplicates") or 0),
        "validity_years": (expires - issued).days / 365.25 if issued and expires else None,
        "expired": bool(expires and expires.date() < today),
        "issued_in_future": bool(issued and issued.date() > today),
        "has_document": text is not None,
        "readable": bool(text and len(text) >= 40),
        "doc_number": found(number),
        "doc_issuer": found(c.get("issuer")),
        "doc_holder": found((c.get("owner") or {}).get("organization")),
        "doc_type": None if not text or kind not in TYPE_WORDS else any(w in plain for w in TYPE_WORDS[kind]),
        "doc_dates": dates_found,
        "doc_target": found((c.get("target") or {}).get("name")),
        "bio_on_conventional": kind == "BIO" and "conventionnelle" in [normalize(m) for m in (c.get("target") or {}).get("production_methods", [])],
    }


def vector(c: dict[str, Any]) -> list[float]:
    f = facts(c)
    tri = lambda value: -1.0 if value is None else float(bool(value))  # noqa: E731
    return [1.0 if f["kind"] == t else 0.0 for t in TYPES] + [
        float(f["issuer"]["recognized"]), float(f["issuer"]["other_type"]), float(f["issuer"]["lookalike"]), f["issuer"]["similarity"],
        float(bool(f["number"])), float(f["number_format_known"]), tri(f["number_format_ok"]),
        -1.0 if f["number_year_gap"] is None else float(f["number_year_gap"]),
        float(f["duplicates"]), -1.0 if f["validity_years"] is None else f["validity_years"],
        float(f["validity_years"] is None), float(f["expired"]), float(f["issued_in_future"]),
        float(f["has_document"]), float(f["readable"]),
        tri(f["doc_number"]), tri(f["doc_issuer"]), tri(f["doc_holder"]), tri(f["doc_type"]), tri(f["doc_dates"]), tri(f["doc_target"]),
        float(f["bio_on_conventional"]),
    ]


def findings(c: dict[str, Any]) -> list[tuple[int, str]]:
    """(points, reason in French) of everything that weakens the certificate."""
    f = facts(c)
    issuer, kind, label = f["issuer"], f["kind"], TYPE_LABELS.get(f["kind"], "")
    out: list[tuple[int, str]] = []
    name = c.get("issuer") or "?"

    if issuer["lookalike"]:
        out.append((3, f"L'organisme « {name} » ressemble à « {issuer['closest']} » sans être identique : possible usurpation ou erreur de saisie."))
    elif issuer["other_type"]:
        out.append((1, f"L'organisme « {name} » est reconnu, mais pas pour un label « {label} »."))
    elif not issuer["recognized"]:
        out.append((2, f"L'organisme « {name} » ne figure pas parmi les organismes reconnus pour un label « {label} »."))

    if not f["number"]:
        out.append((2 if kind in ("BIO", "FAIR_TRADE") else 1, "Aucun numéro de certificat : il ne peut pas être vérifié auprès de l'organisme."))
    elif f["number_format_ok"] is False:
        out.append((2, f"Le numéro « {f['number']} » ne suit pas le format des certificats de {name}."))
    if f["number_year_gap"] is not None and f["number_year_gap"] > 1:
        out.append((1, f"L'année du numéro ne correspond pas à la date de délivrance ({fmt.date(c.get('issue_date'))})."))
    if f["duplicates"]:
        out.append((3, f"Le numéro « {f['number']} » est déjà utilisé par {f['duplicates']} autre(s) certificat(s) sur NutriTrace."))

    if f["validity_years"] is not None and f["validity_years"] > MAX_YEARS.get(kind, 5) + 0.1:
        out.append((1, f"Durée de validité de {fmt.number(f['validity_years'], 1)} ans, inhabituelle pour ce type de label."))
    if f["validity_years"] is None and kind in ("BIO", "FAIR_TRADE"):
        out.append((1, "Aucune date d'expiration, alors que ce type de certificat est renouvelé régulièrement."))
    if f["issued_in_future"]:
        out.append((2, "La date de délivrance est dans le futur."))
    if f["expired"]:
        out.append((1, f"Le certificat est expiré depuis le {fmt.date(c.get('expiration_date'))}."))

    if not f["has_document"]:
        out.append((2, "Aucun justificatif fourni."))
    elif not f["readable"]:
        out.append((1, "Le justificatif n'est pas lisible automatiquement (scan ou image) : vérification manuelle nécessaire."))
    else:
        if f["number"] and f["doc_number"] is False:
            out.append((2, f"Le numéro « {f['number']} » n'apparaît pas dans le justificatif."))
        if f["doc_issuer"] is False:
            out.append((1, f"Le nom de l'organisme « {name} » n'apparaît pas dans le justificatif."))
        if f["doc_holder"] is False:
            out.append((1, f"Le justificatif ne mentionne pas le titulaire « {(c.get('owner') or {}).get('organization')} »."))
        if f["doc_type"] is False:
            out.append((1, f"Le justificatif ne parle pas d'un label « {label} »."))
        if f["doc_dates"] is False:
            out.append((1, "La date de délivrance saisie ne figure pas dans le justificatif."))
    if f["bio_on_conventional"]:
        out.append((2, "Label biologique déclaré sur une production en méthode conventionnelle."))
    return out


def rule_level(c: dict[str, Any]) -> str:
    points = sum(p for p, _ in findings(c))
    return "eleve" if points >= 4 else "moyen" if points >= 2 else "faible"


def reasons(c: dict[str, Any], level: str) -> list[str]:
    found = [text for _, text in sorted(findings(c), key=lambda item: -item[0])]
    if found:
        return found
    f = facts(c)
    ok = ["Organisme reconnu pour ce type de label."]
    if f["readable"]:
        ok.append("Le justificatif reprend le numéro, l'organisme et le titulaire déclarés.")
    return ok


def report(c: dict[str, Any], level: str, risk: float) -> str:
    target = c.get("target") or {}
    lines = [
        f"Certificat « {c.get('name')} » ({TYPE_LABELS.get(c.get('type'), c.get('type'))}) délivré par {c.get('issuer')}, n° {c.get('number') or 'non renseigné'}.",
        f"Titulaire : {(c.get('owner') or {}).get('organization') or '?'}. Couvre : {target.get('kind', '')} {target.get('name', '')}.",
        f"Délivré le {fmt.date(c.get('issue_date'))}, expire le {fmt.date(c.get('expiration_date')) if c.get('expiration_date') else 'non renseigné'}.",
        f"Verdict du modèle : {LEVEL_TEXT[level].upper()} (score {round(risk * 100)} %).",
        "Éléments : " + " ".join(reasons(c, level)),
    ]
    return "\n".join(lines)


ADVICE = {
    "faible": ["Le certificat peut être validé après un contrôle rapide du document.", "Rien ne s'oppose à sa validation."],
    "moyen": ["Avant de valider, vérifiez les points relevés, si besoin auprès de l'organisme certificateur.",
              "Une vérification complémentaire est conseillée avant de prendre une décision."],
    "eleve": ["Il est conseillé de refuser le certificat ou de demander au producteur un justificatif conforme.",
              "Ne validez pas ce certificat sans avoir obtenu la confirmation de l'organisme certificateur."],
}
INTROS = {
    "faible": ["Ce certificat paraît authentique.", "Aucune incohérence notable n'a été relevée sur ce certificat."],
    "moyen": ["Ce certificat présente quelques points à vérifier.", "Le certificat est plausible mais incomplet."],
    "eleve": ["Ce certificat est suspect.", "Plusieurs éléments rendent ce certificat douteux."],
}


def explain(c: dict[str, Any], level: str, rng=None) -> str:
    import random

    rng = rng or random.Random(0)
    return " ".join([rng.choice(INTROS[level]), *reasons(c, level)[:4], rng.choice(ADVICE[level])])
