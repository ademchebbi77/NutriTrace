"""Synthetic declarations, lots to advise and consumer reviews.

Reviews are written from fragments, positive or negative, so the moderation model learns
that a polite criticism is a normal review, and that what must be moderated is the form:
insults, advertising, off-topic texts, personal data.
"""

import random
from typing import Any

from .impact_check import CATEGORIES, INDICATORS, typical

PRODUCTS = {
    "Huiles et olives": ["Huile d'olive extra vierge", "Olives Chemlali", "Olives de table Meski"],
    "Produits laitiers": ["Fromage Sicilien de Béja", "Lait cru de vache", "Yaourt nature"],
    "Céréales": ["Semoule de blé dur", "Couscous moyen", "Blé dur Karim"],
    "Fruits et légumes": ["Tomates de plein champ", "Oranges Maltaises", "Piments Baklouti", "Grenades de Testour"],
    "Miels": ["Miel de thym", "Miel de romarin"],
    "Fruits secs et dattes": ["Dattes Deglet Nour", "Amandes de Kairouan", "Pistaches de Kasserine"],
    "Conserves et épicerie": ["Harissa traditionnelle", "Concentré de tomates"],
}


# --- declarations ---------------------------------------------------------------------------

def declaration(rng: random.Random, kind: str | None = None) -> dict[str, Any]:
    """A declaration of 1 to 4 figures, plausible or with one defect."""
    category = rng.choice(CATEGORIES)
    mass = float(rng.choice([150, 300, 500, 900, 1200, 2000, 5000, 7200]))
    traced = rng.random() < 0.7
    calculated, declared = {}, {}
    for indicator in rng.sample(INDICATORS, rng.randint(1, 4)):
        low, high = typical(category, indicator)
        per_kg = rng.uniform(low, high) if low > 0 else rng.uniform(0, high)
        value = per_kg * mass
        if traced and indicator in ("co2_kg", "water_l", "energy_kwh"):
            calculated[indicator] = round(value * rng.uniform(0.4, 0.95), 2)
        declared[indicator] = {"value": round(value, 2), "source": rng.choice(["MEASURED", "PROVIDED"])}

    kind = kind or rng.choices(["ok", "impossible", "under_declared", "too_high", "too_low"], weights=[45, 8, 17, 15, 15])[0]
    target = rng.choice(list(declared))
    low, high = typical(category, target)
    if kind == "impossible":
        target = "waste_kg"
        declared[target] = {"value": round(mass * rng.uniform(11, 200), 2), "source": rng.choice(["MEASURED", "PROVIDED"])}
    elif kind == "under_declared":
        target = rng.choice(["co2_kg", "water_l", "energy_kwh"])
        base = max(high * mass * rng.uniform(0.3, 0.9), 1.0)
        calculated[target] = round(base, 2)
        declared[target] = {"value": round(base * rng.uniform(0.0, 0.45), 2), "source": rng.choice(["MEASURED", "PROVIDED"])}
    elif kind == "too_high":
        declared[target] = {"value": round(high * mass * rng.uniform(6, 1000), 2), "source": declared[target]["source"]}
        calculated.pop(target, None)
    elif kind == "too_low" and low > 0:
        declared[target] = {"value": round(low * mass * rng.uniform(0.0, 0.18), 3), "source": declared[target]["source"]}
        calculated.pop(target, None)

    return {"id": rng.randint(1, 10_000), "lot": f"LOT-2026-{rng.randint(1, 999):03d}", "product": rng.choice(PRODUCTS[category]),
            "category": category, "mass_kg": mass, "calculated": calculated, "declared": declared}


# --- advice ---------------------------------------------------------------------------------

CONFIG = {
    "thresholds": {"co2_per_kg": {"best": 0.1, "worst": 1.5}, "water_per_kg": {"best": 10, "worst": 150},
                   "energy_per_kg": {"best": 0.05, "worst": 1.5}, "food_miles": {"best": 30, "worst": 400}},
    "weights": {"co2": 0.4, "water": 0.2, "energy": 0.2, "food_miles": 0.2},
    "grades": {"A": 80, "B": 60, "C": 40, "D": 20},
    "factors": {"truck": 0.105, "van": 0.25, "train": 0.028, "ship": 0.016, "plane": 0.602},
}


def advice_case(rng: random.Random) -> dict[str, Any]:
    category = rng.choice(CATEGORIES)
    per_kg = {
        "co2": rng.choice([None, rng.uniform(0.03, 2.5)]) if rng.random() < 0.2 else rng.uniform(0.03, 2.5),
        "water": rng.uniform(0, 400) if rng.random() < 0.8 else None,
        "energy": rng.uniform(0.02, 2.5) if rng.random() < 0.8 else None,
        "food_miles": rng.uniform(0, 900) if rng.random() < 0.85 else 0,
    }
    stages = {k: round(rng.uniform(0, 400), 2) if rng.random() < 0.8 else None
              for k in ("production_co2", "transformation_co2", "transport_co2", "packaging_co2")}
    missing = [i for i in INDICATORS if rng.random() < 0.3]
    return {"id": rng.randint(1, 10_000), "lot": f"LOT-2026-{rng.randint(1, 999):03d}", "product": rng.choice(PRODUCTS[category]),
            "category": category, "per_kg": per_kg, "stages": stages, "missing": missing, "config": CONFIG,
            "transports": [{"mode": rng.choice(["truck", "van", "train", "plane"]), "distance_km": rng.randint(5, 900)} for _ in range(rng.randint(0, 3))]}


# --- reviews --------------------------------------------------------------------------------

GOOD = ["Très bon produit", "Excellent", "Qualité au rendez-vous", "Je recommande", "Parfait", "Délicieux", "Très frais", "Super goût",
        "Bon rapport qualité-prix", "Produit authentique"]
BAD = ["Déçu", "Pas terrible", "Un peu cher", "Goût moyen", "Produit décevant", "Pas assez frais", "Trop salé", "Emballage abîmé",
       "Qualité en baisse", "Je ne rachèterai pas"]
DETAILS = ["la traçabilité est claire", "on sait enfin d'où ça vient", "le parcours est bien documenté", "il manque des données environnementales",
           "l'huile a un bon goût fruité", "les dattes étaient moelleuses", "le fromage était trop sec", "livraison en retard au magasin",
           "la note environnementale est rassurante", "le certificat bio est consultable", "le prix a augmenté", "rien à redire"]
INSULT_LINES = ["bande d'escrocs", "vous êtes des voleurs", "producteur minable", "c'est de la merde", "honte à vous bande de crétins",
                "des abrutis incompétents", "produit pourri par des idiots", "arnaque, ordures"]
SPAM_LINES = ["Gagnez 500 dinars en cliquant ici www.gain-facile.tn", "Promo -70% sur tous nos produits, commandez sur notre site",
              "Achetez vos huiles moins cher chez nous : bonprix.com", "Je vends des dattes en gros, contactez-moi en privé",
              "Code promo NUTRI50 valable sur notre boutique en ligne", "Rejoignez mon groupe pour gagner de l'argent rapidement"]
OFF_TOPIC_LINES = ["Quelqu'un sait quel temps il fera demain à Tunis ?", "Le match d'hier était incroyable", "Je cherche un appartement à louer à Sfax",
                   "Bonne année à tous !", "Vive les vacances à Djerba", "Qui a vu le dernier film au cinéma ?", "Ma voiture est en panne depuis hier",
                   "Je n'ai pas encore goûté, je donnerai mon avis plus tard"]
# Combined from parts so the model learns the topics, not a few fixed sentences.
OFF_TOPIC_SUBJECTS = ["il pleut", "il fait très chaud", "la météo", "le match de foot", "l'Espérance", "le Club Africain", "mon fils", "ma voisine",
                      "les élections", "le bac", "mon travail", "le loyer", "les embouteillages", "mon téléphone", "la série d'hier soir",
                      "les vacances", "mon chat", "le mariage de mon cousin", "le prix de l'essence", "la plage"]
OFF_TOPIC_ENDS = ["aujourd'hui chez moi", "depuis ce matin", "c'est incroyable", "je n'en peux plus", "quelqu'un a un avis ?", "trop bien",
                  "c'était hier soir", "à Tunis", "ce week-end", "franchement"]
CONTACTS = ["Appelez-moi au 98 765 432", "mon numéro : +216 22 334 455", "écrivez-moi à sami.belhadj@gmail.com", "contact : 71 234 567",
            "mon mail est amel.bs@yahoo.fr", "joignable au 55.443.322"]


def review(rng: random.Random, kind: str | None = None) -> dict[str, Any]:
    kind = kind or rng.choices(["ok", "insult", "spam", "off_topic", "personal_data"], weights=[55, 12, 11, 11, 11])[0]
    positive = rng.random() < 0.65
    rating = rng.choice([4, 5]) if positive else rng.choice([1, 2, 3])
    opinion = f"{rng.choice(GOOD if positive else BAD)}, {rng.choice(DETAILS)}."
    if kind == "ok":
        comment = opinion if rng.random() < 0.7 else f"{opinion} {rng.choice(DETAILS).capitalize()}."
    elif kind == "insult":
        rating = rng.choice([1, 2])
        comment = rng.choice([f"{rng.choice(INSULT_LINES).capitalize()} !", f"{rng.choice(BAD)}, {rng.choice(INSULT_LINES)}."])
    elif kind == "spam":
        comment = rng.choice(SPAM_LINES)
    elif kind == "off_topic":
        comment = rng.choice(OFF_TOPIC_LINES) if rng.random() < 0.3 else f"{rng.choice(OFF_TOPIC_SUBJECTS).capitalize()} {rng.choice(OFF_TOPIC_ENDS)}."
    else:
        comment = f"{opinion} {rng.choice(CONTACTS)}."
    if rng.random() < 0.15:
        comment = comment.lower()
    category = rng.choice(CATEGORIES)
    return {"id": rng.randint(1, 10_000), "rating": rating, "comment": comment, "product": rng.choice(PRODUCTS[category]), "kind": kind}
