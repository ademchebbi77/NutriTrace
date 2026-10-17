"""Train the two scikit-learn models of the Transformation - Lots module.

    python scripts/train_transformation_models.py [--transformations 6000] [--lots 5000]

- transformation check (normal or the kind of anomaly): real transformations exported by
  `php artisan ai:export` + synthetic ones, normal or with an injected anomaly; labels from
  the reference rules of transformations.py;
- shelf life (regression, in days): real lots with a date limit + synthetic lots following
  the typical shelf lives of shelf_life.py. Its errors are reported in days and on the real lots.
"""

import argparse
import json
import math
import random
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
sys.path.insert(0, str(Path(__file__).resolve().parent))

import numpy as np  # noqa: E402
from sklearn.metrics import mean_absolute_error, r2_score  # noqa: E402
from sklearn.model_selection import train_test_split  # noqa: E402

from nutritrace_ai import shelf_life, transformations  # noqa: E402
from nutritrace_ai import synth_transformation as synth  # noqa: E402
from nutritrace_ai.config import EXPORT_DIR, SHELF_LIFE_MODEL_PATH, TRANSFORMATION_MODEL_PATH  # noqa: E402
from nutritrace_ai.transformation_models import ShelfLifeModel, TransformationModel, build_regressor  # noqa: E402
from train_distribution_models import load, train  # noqa: E402


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--transformations", type=int, default=6000)
    parser.add_argument("--lots", type=int, default=5000)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()
    rng = random.Random(args.seed)

    real_transformations, real_lots = load("transformations.json"), load("lots_expiry.json")
    print(f"Real data exported from NutriTrace: {len(real_transformations)} transformations, {len(real_lots)} lots"
          + ("" if real_transformations else " (run php artisan ai:export first)"))

    samples = real_transformations * 5 + [synth.transformation(rng) for _ in range(args.transformations)]
    train("Transformation check", TransformationModel, samples, real_transformations, transformations.vector,
          transformations.rule_label, transformations.LABELS, TRANSFORMATION_MODEL_PATH, args.seed)

    # Shelf life: the real lots keep their own date limit as the target.
    print("\n=== Shelf life (regression) ===")
    dated = [lot for lot in real_lots if shelf_life.actual_days(lot) and shelf_life.actual_days(lot) > 0]
    lots = [{**lot, "true_days": shelf_life.actual_days(lot)} for lot in dated] * 5 + [synth.lot(rng) for _ in range(args.lots)]
    X = np.array([shelf_life.vector(lot) for lot in lots])
    y = np.array([math.log(lot["true_days"]) for lot in lots])
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=args.seed)
    regressor = build_regressor(args.seed).fit(X_train, y_train)
    predicted = regressor.predict(X_test)
    days_true, days_pred = np.exp(y_test), np.exp(predicted)
    # Relative error: 10 days off matters for cheese, not for honey.
    relative = float(np.median(np.abs(days_pred - days_true) / days_true))
    metrics = {"r2_log": float(r2_score(y_test, predicted)), "mae_days": float(mean_absolute_error(days_true, days_pred)),
               "median_relative_error": relative, "train_size": len(y_train), "test_size": len(y_test)}
    if dated:
        model = ShelfLifeModel(regressor)
        labels = [model.predict(lot)["label"] for lot in dated]
        metrics["real_plausible_share"] = labels.count("plausible") / len(labels)
        metrics["real_lots"] = len(dated)
        print(f"Real lots judged plausible: {metrics['real_plausible_share']:.1%} of {len(dated)}")
    print(f"R² (log days): {metrics['r2_log']:.3f} · median relative error: {relative:.1%} · MAE: {metrics['mae_days']:.0f} days")
    # Shown on the admin page with the other models.
    metrics["accuracy"] = 1 - relative
    metrics["macro_f1"] = metrics["r2_log"]

    ShelfLifeModel(regressor).save(SHELF_LIFE_MODEL_PATH, metrics)
    SHELF_LIFE_MODEL_PATH.with_name("shelf-life-metrics.json").write_text(json.dumps(metrics, indent=2), encoding="utf-8")
    print(f"Model saved to {SHELF_LIFE_MODEL_PATH}")


if __name__ == "__main__":
    main()
