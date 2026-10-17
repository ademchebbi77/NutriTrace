"""Train the transport anomaly model (random forest, scikit-learn).

    python scripts/train_anomaly.py [--normal 4000] [--per-anomaly 700]

Training set: the real transports exported from NutriTrace (php artisan ai:export),
synthetic plausible transports, borderline-but-normal variations and copies with one
injected anomaly of each kind. Labels come from the reference rules of features.py.
Metrics are printed and saved next to the model (models/anomaly-metrics.json).
"""

import argparse
import json
import random
import sys
from datetime import timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import numpy as np  # noqa: E402
from sklearn.metrics import classification_report, confusion_matrix  # noqa: E402
from sklearn.model_selection import train_test_split  # noqa: E402

from nutritrace_ai import synth  # noqa: E402
from nutritrace_ai.anomaly import AnomalyDetector, build_model  # noqa: E402
from nutritrace_ai.config import ANOMALY_MODEL_PATH, EXPORT_DIR  # noqa: E402
from nutritrace_ai.features import FEATURE_NAMES, LABELS, rule_label, vector  # noqa: E402
from nutritrace_ai.formatting import parse_date  # noqa: E402


def borderline(rng: random.Random, t: dict) -> dict:
    """Unusual but acceptable transport: long detour or fast trip within the limits."""
    t = dict(t)
    if t.get("distance_km") and rng.random() < 0.5:
        t["distance_km"] = round(t["distance_km"] * rng.uniform(1.3, 1.7), 2)
    if t.get("arrival_date") and t.get("distance_km"):
        departure = parse_date(t["departure_date"])
        speed = {"truck": 90, "van": 100, "train": 160, "ship": 45, "plane": 800}[t["mode"]] * rng.uniform(0.6, 0.97)
        t["arrival_date"] = (departure + timedelta(hours=t["distance_km"] / speed)).isoformat()
    return t


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--normal", type=int, default=4000, help="synthetic normal transports")
    parser.add_argument("--per-anomaly", type=int, default=700, help="examples of each anomaly kind")
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()
    rng = random.Random(args.seed)

    export = EXPORT_DIR / "transports.json"
    real = json.loads(export.read_text(encoding="utf-8")) if export.exists() else []
    print(f"Real transports exported from NutriTrace: {len(real)}" + ("" if real else " (run php artisan ai:export first for real data)"))

    normal = [t for t in real if t.get("distance_km") is not None] + synth.normal_transports(rng, args.normal)
    samples = list(real) + normal + [borderline(rng, rng.choice(normal)) for _ in range(args.normal // 3)]
    for kind in LABELS[1:]:
        samples += [synth.inject(rng, rng.choice(normal), kind) for _ in range(args.per_anomaly)]

    X = np.array([vector(t) for t in samples])
    y = np.array([rule_label(t) for t in samples])
    print("Examples per label:", {label: int((y == label).sum()) for label in LABELS})

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, stratify=y, random_state=args.seed)
    model = build_model(args.seed).fit(X_train, y_train)
    predicted = model.predict(X_test)

    print("\nTest set (20 %, never seen in training):")
    print(classification_report(y_test, predicted, digits=3, zero_division=0))
    labels = [label for label in LABELS if label in set(y)]
    print("Confusion matrix (rows = truth, columns = prediction):", labels)
    print(confusion_matrix(y_test, predicted, labels=labels))

    report = classification_report(y_test, predicted, output_dict=True, zero_division=0)
    real_accuracy = float((model.predict(np.array([vector(t) for t in real])) == np.array([rule_label(t) for t in real])).mean()) if real else None
    if real_accuracy is not None:
        print(f"\nAccuracy on the real NutriTrace transports: {real_accuracy:.3f}")

    importances = sorted(zip(FEATURE_NAMES, model.feature_importances_), key=lambda pair: -pair[1])
    print("Most useful features:", ", ".join(f"{name} {value:.2f}" for name, value in importances[:6]))

    metrics = {
        "accuracy": report["accuracy"],
        "macro_f1": report["macro avg"]["f1-score"],
        "per_label": {label: report[label] for label in labels},
        "real_accuracy": real_accuracy,
        "train_size": int(len(y_train)),
        "test_size": int(len(y_test)),
        "feature_importances": dict(importances),
    }
    detector = AnomalyDetector(model)
    detector.save(ANOMALY_MODEL_PATH, metrics)
    ANOMALY_MODEL_PATH.with_name("anomaly-metrics.json").write_text(json.dumps(metrics, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"\nModel saved to {ANOMALY_MODEL_PATH}")


if __name__ == "__main__":
    main()
