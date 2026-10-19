"""Moderation of consumer reviews: a text classifier (TF-IDF + logistic regression) that
tells a normal review from an insulting one, spam or advertising, an off-topic text, or
a review that publishes personal data. Rules catch phone numbers, e-mails and links,
which a classifier could miss.

A negative but polite review ("décevant, trop cher") is a normal review: only the form
is moderated, never the opinion.
"""

import random
import re
import unicodedata
from typing import Any

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import FeatureUnion, Pipeline

LABELS = ["ok", "insult", "spam", "off_topic", "personal_data"]
LABEL_TEXT = {"ok": "avis conforme", "insult": "propos injurieux", "spam": "publicité ou spam", "off_topic": "hors sujet",
              "personal_data": "données personnelles"}

# Written without nested or overlapping repetitions, so matching stays linear on any text.
EMAIL = re.compile(r"[\w.+-]+@[\w-]+(?:\.[\w-]+)+")
# Tunisian numbers: 8 digits written 2-3-3 or 2-2-2-2, optionally after +216.
PHONE = re.compile(r"(?<!\d)(?:\+?216[ .-]?)?(?:\d{2}[ .-]?\d{3}[ .-]?\d{3}|\d{2}[ .-]?\d{2}[ .-]?\d{2}[ .-]?\d{2})(?!\d)")
LINK = re.compile(r"(https?://|www\.|\.com\b|\.tn\b|\.fr\b)", re.I)
INSULTS = ["connard", "connards", "salaud", "salauds", "escroc", "escrocs", "voleur", "voleurs", "idiot", "idiots", "abruti", "abrutis",
           "crétin", "crétins", "imbécile", "imbéciles", "minable", "minables", "pourri", "pourris", "ordure", "ordures", "merde", "nul à chier",
           "bande de", "honte à vous", "dégage", "incompétents"]


def normalize(text: str) -> str:
    text = unicodedata.normalize("NFD", (text or "").lower())
    return "".join(c for c in text if unicodedata.category(c) != "Mn")


def build() -> Pipeline:
    features = FeatureUnion([
        ("words", TfidfVectorizer(preprocessor=normalize, ngram_range=(1, 2), min_df=1, sublinear_tf=True)),
        ("chars", TfidfVectorizer(preprocessor=normalize, analyzer="char_wb", ngram_range=(3, 5), min_df=2, sublinear_tf=True)),
    ])
    return Pipeline([("features", features), ("model", LogisticRegression(max_iter=2000, C=4.0, class_weight="balanced"))])


def rule_findings(text: str) -> list[tuple[str, str]]:
    """(label, reason) found by explicit rules."""
    out = []
    if EMAIL.search(text or ""):
        out.append(("personal_data", "Le commentaire contient une adresse e-mail."))
    if PHONE.search(text or ""):
        out.append(("personal_data", "Le commentaire contient un numéro de téléphone."))
    if LINK.search(text or ""):
        out.append(("spam", "Le commentaire contient un lien vers un site."))
    words = [w for w in INSULTS if re.search(rf"\b{re.escape(normalize(w))}\b", normalize(text))]
    if words:
        out.append(("insult", f"Termes injurieux : {', '.join(words[:3])}."))
    return out


def judge(pipeline: Pipeline | None, review: dict[str, Any]) -> dict[str, Any]:
    """Decision for one review: label, confidence, flagged, reasons."""
    text = (review.get("comment") or "").strip()
    if not text:
        return {"label": "ok", "confidence": 1.0, "flagged": False, "reasons": ["Note sans commentaire."]}

    found = rule_findings(text)
    if pipeline is not None:
        probabilities = dict(zip(pipeline.classes_, pipeline.predict_proba([text])[0]))
        label = max(probabilities, key=probabilities.get)
        confidence = float(probabilities[label])
    else:
        label, confidence = "ok", 0.5

    # Explicit rules win: an e-mail is personal data whatever the model thinks.
    if found:
        label, confidence = found[0][0], max(confidence if label == found[0][0] else 0.0, 0.95)
    flagged = label != "ok" and confidence >= 0.5
    reasons = [reason for _, reason in found] or ([f"Le modèle reconnaît un texte de type « {LABEL_TEXT[label]} »."] if flagged else ["Avis conforme : il porte sur le produit, sans propos injurieux ni données personnelles."])
    return {"label": label if flagged else "ok", "confidence": round(confidence, 4), "flagged": flagged, "reasons": reasons}


def report(review: dict[str, Any], result: dict[str, Any]) -> str:
    return "\n".join([
        f"Avis de {review.get('rating', '?')}/5 sur le produit {review.get('product') or '?'}.",
        f"Commentaire : « {review.get('comment') or ''} »",
        f"Verdict du modèle : {'À MODÉRER' if result['flagged'] else 'CONFORME'} ({LABEL_TEXT[result['label']]}), confiance {round(result['confidence'] * 100)} %.",
        "Éléments : " + " ".join(result["reasons"]),
    ])


ADVICE = {
    "ok": ["Il peut être publié.", "Rien ne s'oppose à sa publication."],
    "insult": ["Il est conseillé de le refuser : les propos injurieux ne sont pas acceptés, même pour exprimer une critique.",
               "Refusez-le ; le consommateur pourra publier un avis critique formulé poliment."],
    "spam": ["Il est conseillé de le refuser : il s'agit de publicité, pas d'un avis sur le produit.", "Refusez-le, ce texte fait la promotion d'autre chose."],
    "off_topic": ["Il est conseillé de le refuser : il ne parle pas du produit.", "Refusez-le s'il n'apporte aucune information sur le produit."],
    "personal_data": ["Il est conseillé de le refuser : il expose des données personnelles qui ne doivent pas être publiées.",
                      "Refusez-le pour protéger les coordonnées qu'il contient."],
}
INTROS = {"ok": ["Cet avis est conforme.", "Cet avis ne pose pas de problème."],
          "flagged": ["Cet avis a été retenu pour modération.", "Cet avis ne peut pas être publié en l'état."]}


def explain(review: dict[str, Any], result: dict[str, Any], rng: random.Random | None = None) -> str:
    rng = rng or random.Random(0)
    return " ".join([rng.choice(INTROS["flagged" if result["flagged"] else "ok"]), *result["reasons"][:2], rng.choice(ADVICE[result["label"]])])
