"""Train the two scikit-learn models of the Distribution - Certification module.

    python scripts/train_distribution_models.py [--certificates 6000] [--receptions 6000]

- certificate risk (faible / moyen / eleve): real certificates exported by `php artisan ai:export`
  (with the text of their PDF proof) + synthetic certificates, genuine or with defects;
- reception check (accepter / reserves / refuser): real pending and past receptions + synthetic ones.

Labels come from the reference rules (certificates.py, reception.py). Metrics are printed and
saved next to each model.
"""

import argparse
import json
import random
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import numpy as np  # noqa: E402
from sklearn.metrics import classification_report, confusion_matrix  # noqa: E402
from sklearn.model_selection import train_test_split  # noqa: E402

from nutritrace_ai import certificates, reception  # noqa: E402
from nutritrace_ai import synth_distribution as synth  # noqa: E402
from nutritrace_ai.config import CERT_RISK_MODEL_PATH, EXPORT_DIR, RECEPTION_MODEL_PATH  # noqa: E402
from nutritrace_ai.distribution_models import CertificateRiskModel, ReceptionModel  # noqa: E402
from nutritrace_ai.pdf_text import extract  # noqa: E402
from nutritrace_ai.tabular import build_model  # noqa: E402


def load(name: str) -> list[dict]:
    path = EXPORT_DIR / name
    return json.loads(path.read_text(encoding="utf-8")) if path.exists() else []


def train(title: str, cls, samples: list, real: list, vector, label, order: list[str], path: Path, seed: int) -> dict:
    X = np.array([vector(s) for s in samples])
    y = np.array([label(s) for s in samples])
    print(f"\n=== {title} ===\nExamples per label:", {name: int((y == name).sum()) for name in order})

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, stratify=y, random_state=seed)
    model = build_model(seed).fit(X_train, y_train)
    predicted = model.predict(X_test)
    print("Test set (20 %, never seen in training):")
    print(classification_report(y_test, predicted, digits=3, zero_division=0))
    labels = [name for name in order if name in set(y)]
    print("Confusion matrix (rows = truth, columns = prediction):", labels)
    print(confusion_matrix(y_test, predicted, labels=labels))

    real_accuracy = None
    if real:
        real_accuracy = float((model.predict(np.array([vector(s) for s in real])) == np.array([label(s) for s in real])).mean())
        print(f"Accuracy on the {len(real)} real NutriTrace cases: {real_accuracy:.3f}")

    report = classification_report(y_test, predicted, output_dict=True, zero_division=0)
    metrics = {"accuracy": report["accuracy"], "macro_f1": report["macro avg"]["f1-score"],
               "per_label": {name: report[name] for name in labels}, "real_accuracy": real_accuracy,
               "real_cases": len(real), "train_size": int(len(y_train)), "test_size": int(len(y_test))}
    cls(model).save(path, metrics)
    path.with_name(path.stem + "-metrics.json").write_text(json.dumps(metrics, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"Model saved to {path}")
    return metrics


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--certificates", type=int, default=6000)
    parser.add_argument("--receptions", type=int, default=6000)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()
    rng = random.Random(args.seed)

    real_certs = load("certifications.json")
    for c in real_certs:
        # Read each PDF once: the features look at its text several times.
        c["document"] = {**(c.get("document") or {}), "text": extract(c.get("document"))} if (c.get("document") or {}).get("present") else c.get("document")
    real_receptions = load("receptions.json")
    print(f"Real data exported from NutriTrace: {len(real_certs)} certificates, {len(real_receptions)} receptions"
          + ("" if real_certs or real_receptions else " (run php artisan ai:export first)"))

    certs = real_certs * 5 + [synth.certificate(rng) for _ in range(args.certificates)]
    train("Certificate risk", CertificateRiskModel, certs, real_certs, certificates.vector, certificates.rule_level,
          certificates.LEVELS, CERT_RISK_MODEL_PATH, args.seed)

    receptions = real_receptions * 5 + [synth.reception(rng) for _ in range(args.receptions)]
    train("Reception check", ReceptionModel, receptions, real_receptions, reception.vector, reception.rule_decision,
          reception.DECISIONS, RECEPTION_MODEL_PATH, args.seed)


if __name__ == "__main__":
    main()
