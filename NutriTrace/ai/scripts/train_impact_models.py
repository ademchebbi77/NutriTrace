"""Train the two scikit-learn models of the Impact environnemental - Avis et signalements module.

    python scripts/train_impact_models.py [--declarations 6000] [--reviews 6000]

- impact check (one row per declared figure: ok / impossible / under_declared / too_high / too_low):
  real declarations exported by `php artisan ai:export` + synthetic ones; labels from impact_check.py;
- review moderation (ok / insult / spam / off_topic / personal_data): TF-IDF + logistic regression
  on synthetic reviews and the real NutriTrace reviews (all published, so labelled ok). Its accuracy
  is also measured on hand-written sentences that are not built from the generator's fragments.
"""

import argparse
import json
import random
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
sys.path.insert(0, str(Path(__file__).resolve().parent))

from sklearn.metrics import classification_report  # noqa: E402
from sklearn.model_selection import train_test_split  # noqa: E402

from nutritrace_ai import impact_check, moderation  # noqa: E402
from nutritrace_ai import synth_impact as synth  # noqa: E402
from nutritrace_ai.config import IMPACT_CHECK_MODEL_PATH, MODERATION_MODEL_PATH  # noqa: E402
from nutritrace_ai.impact_models import ImpactCheckModel, ModerationModel  # noqa: E402
from train_distribution_models import load, train  # noqa: E402

# Written by hand, independent of synth_impact.py: an honest check of how the model generalises.
HELD_OUT = [
    ("Huile très parfumée, un peu chère mais ça vaut le coup.", "ok"), ("Pas convaincu par le goût, assez fade cette fois.", "ok"),
    ("Le fromage avait un goût bizarre, je suis déçue.", "ok"), ("Merci pour la transparence, on voit tout le parcours du lot.", "ok"),
    ("Livré abîmé, emballage écrasé, dommage.", "ok"), ("Bof, rien d'exceptionnel pour ce prix.", "ok"),
    ("Vous êtes vraiment des escrocs et des menteurs", "insult"), ("Producteur incompétent, bande d'idiots", "insult"),
    ("Quelle bande d'abrutis, produit de merde", "insult"),
    ("Découvrez nos offres sur www.super-promo.tn", "spam"), ("Gagnez de l'argent depuis chez vous, contactez-moi vite", "spam"),
    ("Code promo sur ma boutique en ligne, -50 % sur tout", "spam"),
    ("Il fait vraiment froid ce soir à Bizerte", "off_topic"), ("Le match de l'Étoile était nul hier soir", "off_topic"),
    ("Mon frère se marie samedi prochain", "off_topic"),
    ("Bon produit, écrivez-moi à karim.t@hotmail.com", "personal_data"), ("Super dattes, mon numéro c'est le 97 112 233", "personal_data"),
]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--declarations", type=int, default=6000)
    parser.add_argument("--reviews", type=int, default=6000)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()
    rng = random.Random(args.seed)

    real_declarations, real_reviews = load("declarations.json"), load("reviews.json")
    print(f"Real data exported from NutriTrace: {len(real_declarations)} declarations, {len(real_reviews)} reviews"
          + ("" if real_reviews else " (run php artisan ai:export first)"))

    rows = [row for d in real_declarations * 5 + [synth.declaration(rng) for _ in range(args.declarations)] for row in impact_check.rows(d)]
    real_rows = [row for d in real_declarations for row in impact_check.rows(d)]
    train("Impact check (per declared figure)", ImpactCheckModel, rows, real_rows, impact_check.vector, impact_check.rule_label,
          impact_check.LABELS, IMPACT_CHECK_MODEL_PATH, args.seed)

    print("\n=== Review moderation ===")
    reviews = [synth.review(rng) for _ in range(args.reviews)]
    texts = [r["comment"] for r in reviews] + [r["comment"] for r in real_reviews if r.get("comment")] * 3
    labels = [r["kind"] for r in reviews] + ["ok"] * (3 * sum(1 for r in real_reviews if r.get("comment")))
    X_train, X_test, y_train, y_test = train_test_split(texts, labels, test_size=0.2, stratify=labels, random_state=args.seed)
    pipeline = moderation.build().fit(X_train, y_train)
    print(classification_report(y_test, pipeline.predict(X_test), digits=3, zero_division=0))

    report = classification_report(y_test, pipeline.predict(X_test), output_dict=True, zero_division=0)
    held = [moderation.judge(pipeline, {"comment": text})["label"] == label for text, label in HELD_OUT]
    real_ok = [not moderation.judge(pipeline, r)["flagged"] for r in real_reviews if r.get("comment")]
    metrics = {"accuracy": report["accuracy"], "macro_f1": report["macro avg"]["f1-score"],
               "held_out_accuracy": sum(held) / len(held), "real_accuracy": sum(real_ok) / len(real_ok) if real_ok else None,
               "real_cases": len(real_ok), "train_size": len(X_train), "test_size": len(X_test)}
    print(f"Hand-written sentences (not from the generator): {sum(held)}/{len(held)} correct")
    for (text, label), ok in zip(HELD_OUT, held):
        if not ok:
            print(f"  missed ({label}): {text}")
    if real_ok:
        print(f"Real NutriTrace reviews kept published: {sum(real_ok)}/{len(real_ok)}")

    ModerationModel(pipeline).save(MODERATION_MODEL_PATH, metrics)
    MODERATION_MODEL_PATH.with_name("moderation-metrics.json").write_text(json.dumps(metrics, indent=2), encoding="utf-8")
    print(f"Model saved to {MODERATION_MODEL_PATH}")


if __name__ == "__main__":
    main()
