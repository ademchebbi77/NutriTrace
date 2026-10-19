"""Production forecasting for NutriTrace.

Given a producer's historical production records (same product, same unit),
this module computes:
  - historical statistics (mean, min, max, last, trend)
  - a statistical baseline (trailing mean) used only as a reference
  - a trained ML forecast when enough observations exist AND the model can be
    evaluated adequately on a chronological validation set AND it beats the
    baseline. Otherwise the historical statistics are displayed with an
    explicit explanation — never a fabricated prediction.

The data comes from the Laravel endpoint POST /production/forecast as a list
of records like:
    {
        "production_date": "2024-06-15",
        "quantity": 3500.0,
        "unit": "kg",
        "production_method": "organic"
    }

All records in a single request MUST share the same unit (enforced by Laravel).
Never mix kg, t, L and unit in a single forecast.

Minimum data thresholds
-----------------------
STAT_MIN   : minimum records to compute basic statistics
TREND_MIN  : minimum records to compute a meaningful trend
MODEL_MIN  : minimum records to *attempt* ML training (preliminary threshold)
TEST_MIN   : minimum records in the chronological validation set for an
             adequate evaluation

Design notes
------------
- Records are sorted chronologically before any computation.
- Exact duplicates (same date + quantity + method) are removed.
- The train/test split is always chronological (no random shuffle).
- The model is compared against a naive baseline (trailing mean) on the
  validation set and is only used if it strictly improves the MAE on
  validation data that actually varies.
- No synthetic data is generated; if data is insufficient the response says so.
- `prediction` is only set when a trained model is used. A simple average is
  reported as a statistic (`statistics.mean` / `baseline_estimate`), never as
  a prediction.
"""

from __future__ import annotations

import math
from datetime import datetime
from typing import Any

import numpy as np

# --------------------------------------------------------------------------
# Thresholds
# --------------------------------------------------------------------------

STAT_MIN = 2    # need at least 2 records for mean / std
TREND_MIN = 3   # need at least 3 records to fit a trend line
MODEL_MIN = 10  # preliminary minimum to attempt ML with chronological split
TEST_MIN = 3    # minimum validation records for an adequate evaluation

# --------------------------------------------------------------------------
# Data preparation helpers
# --------------------------------------------------------------------------

_METHOD_CODE = {
    "conventional": 0,
    "organic": 1,
    "integrated": 2,
    "permaculture": 3,
    "other": 4,
}


def _parse_date(value: str) -> datetime:
    return datetime.fromisoformat(value[:10])


def _ordinal_year(dt: datetime) -> float:
    """Fractional year, e.g. 2024.45 for mid-June 2024."""
    start = datetime(dt.year, 1, 1)
    end = datetime(dt.year + 1, 1, 1)
    return dt.year + (dt - start).days / (end - start).days


def _prepare(records: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Sort by date, drop invalid entries and exact duplicates, attach fields."""
    out = []
    seen: set[tuple[datetime, float, int]] = set()
    for r in records:
        try:
            dt = _parse_date(r["production_date"])
            qty = float(r["quantity"])
        except (KeyError, ValueError, TypeError):
            continue
        if not math.isfinite(qty) or qty <= 0:
            continue
        method_code = _METHOD_CODE.get(r.get("production_method", ""), 4)
        key = (dt, qty, method_code)
        if key in seen:
            # Exact duplicate (same date, quantity and method): keep the first.
            continue
        seen.add(key)
        out.append({
            "date": dt,
            "year_frac": _ordinal_year(dt),
            "month": dt.month,
            "quantity": qty,
            "method_code": method_code,
        })
    return sorted(out, key=lambda x: x["date"])


def _make_features(record: dict[str, Any]) -> list[float]:
    """Feature vector for the regression model (no target leakage)."""
    return [
        record["year_frac"],
        math.sin(2 * math.pi * record["month"] / 12),   # seasonal
        math.cos(2 * math.pi * record["month"] / 12),
        float(record["method_code"]),
    ]


# --------------------------------------------------------------------------
# Statistical summary
# --------------------------------------------------------------------------

def _statistics(quantities: list[float]) -> dict[str, Any]:
    arr = np.array(quantities)
    return {
        "count": len(arr),
        "mean": float(np.mean(arr)),
        "std": float(np.std(arr, ddof=1)) if len(arr) >= 2 else None,
        "min": float(np.min(arr)),
        "max": float(np.max(arr)),
        "last": float(arr[-1]),
    }


def _trend_slope(prepared: list[dict[str, Any]]) -> float | None:
    """Ordinary-least-squares slope of quantity vs fractional year."""
    if len(prepared) < TREND_MIN:
        return None
    x = np.array([r["year_frac"] for r in prepared])
    y = np.array([r["quantity"] for r in prepared])
    x_c = x - x.mean()
    denom = float(np.dot(x_c, x_c))
    if denom == 0:
        return None
    return float(np.dot(x_c, y) / denom)


def _trend_description(slope: float | None, mean: float) -> str | None:
    if slope is None:
        return None
    if abs(slope) < mean * 0.02:
        return "stable"
    return "increasing" if slope > 0 else "decreasing"


def _baseline_forecast(quantities: list[float], n: int = 3) -> float:
    """Simple baseline: mean of the last n observations."""
    window = quantities[-n:] if len(quantities) >= n else quantities
    return float(np.mean(window))


# --------------------------------------------------------------------------
# ML model (scikit-learn GradientBoostingRegressor)
# --------------------------------------------------------------------------

def _train_and_evaluate(
    prepared: list[dict[str, Any]],
) -> dict[str, Any] | None:
    """
    Train a GradientBoostingRegressor with a chronological train/test split.
    Returns None if there are fewer than MODEL_MIN records or the validation
    set would be too small to evaluate adequately.
    """
    if len(prepared) < MODEL_MIN:
        return None

    from sklearn.ensemble import GradientBoostingRegressor
    from sklearn.metrics import mean_absolute_error

    n = len(prepared)
    test_size = max(TEST_MIN, int(round(n * 0.2)))
    if test_size >= n - 1:
        return None  # train set would be too small to fit anything meaningful
    split = n - test_size
    train = prepared[:split]
    test = prepared[split:]

    X_train = np.array([_make_features(r) for r in train])
    y_train = np.array([r["quantity"] for r in train])
    X_test = np.array([_make_features(r) for r in test])
    y_test = np.array([r["quantity"] for r in test])

    model = GradientBoostingRegressor(
        n_estimators=100, max_depth=3, learning_rate=0.1, random_state=42
    )
    model.fit(X_train, y_train)
    y_pred = model.predict(X_test)

    model_mae = float(mean_absolute_error(y_test, y_pred))

    # Baseline on the same test window
    baseline_pred = np.full(len(y_test), _baseline_forecast([r["quantity"] for r in train]))
    baseline_mae = float(mean_absolute_error(y_test, baseline_pred))

    test_std = float(np.std(y_test)) if len(y_test) > 1 else 0.0

    # Use the model only if it strictly beats the baseline on a validation
    # set that actually varies (a constant set proves nothing).
    use_model = test_std > 0 and model_mae < baseline_mae

    return {
        "model": model,
        "model_mae": model_mae,
        "baseline_mae": baseline_mae,
        "train_size": len(train),
        "test_size": len(test),
        "test_std": test_std,
        "use_model": use_model,
    }


def _next_period(prepared: list[dict[str, Any]]) -> str:
    """Label of the next expected production period: same month next year."""
    last = prepared[-1]
    next_dt = datetime(last["date"].year + 1, last["date"].month, 1)
    return next_dt.strftime("%Y-%m")


def _predict_next(
    prepared: list[dict[str, Any]],
    eval_result: dict[str, Any],
) -> float:
    """Predict quantity for the next expected production period."""
    next_dt = datetime.strptime(_next_period(prepared) + "-01", "%Y-%m-%d")
    next_record = {
        "year_frac": _ordinal_year(next_dt),
        "month": next_dt.month,
        "method_code": prepared[-1]["method_code"],
    }
    X_next = np.array([_make_features(next_record)])
    model = eval_result["model"]
    return float(model.predict(X_next)[0])


# --------------------------------------------------------------------------
# French text helpers
# --------------------------------------------------------------------------

def _fmt_qty(value: float) -> str:
    """French-style quantity without decimals when the value is integral."""
    if abs(value - round(value)) < 1e-9:
        return str(int(round(value)))
    return f"{round(value, 1):.1f}".replace(".", ",")


def _period_label(period: str) -> str:
    """"2027-10" -> "10/2027"."""
    year, month = period.split("-")
    return f"{month}/{year}"


# --------------------------------------------------------------------------
# Public API
# --------------------------------------------------------------------------

def forecast(
    records: list[dict[str, Any]],
    unit: str,
) -> dict[str, Any]:
    """
    Main entry point called by server.py.

    Parameters
    ----------
    records : list of production dicts from Laravel
    unit    : the unit string (kg / t / L / unit) — same for all records

    Returns
    -------
    A structured dict with keys:
      status, unit, history, statistics, trend_per_year, trend_direction,
      baseline_estimate, prediction, prediction_period, model_info, explanation

    Semantics
    ---------
    - status "insufficient_data" : fewer than STAT_MIN valid records, no stats.
    - status "statistical_only"  : statistics computed, but not enough data to
      evaluate an ML model; `prediction` is always None here.
    - status "ok"                : an ML model was attempted; `prediction` is
      set only when the model beat the baseline on chronological validation,
      otherwise the statistical baseline is displayed with an explanation.
    """
    prepared = _prepare(records)
    n = len(prepared)

    history = [
        {
            "date": r["date"].strftime("%Y-%m-%d"),
            "quantity": r["quantity"],
            "method": next(
                (k for k, v in _METHOD_CODE.items() if v == r["method_code"]), "other"
            ),
        }
        for r in prepared
    ]

    # ---- Not enough data for any statistics ----
    if n < STAT_MIN:
        return {
            "status": "insufficient_data",
            "unit": unit,
            "history": history,
            "statistics": None,
            "trend_per_year": None,
            "trend_direction": None,
            "baseline_estimate": None,
            "prediction": None,
            "prediction_period": None,
            "model_info": {
                "name": None,
                "trained": False,
                "reason": (
                    f"Only {n} valid record(s) found. At least {STAT_MIN} are needed "
                    f"for basic statistics, {MODEL_MIN} to attempt ML forecasting."
                ),
            },
            "explanation": (
                f"Il n'y a que {n} production(s) enregistrée(s) pour ce produit "
                f"avec cette unité. Aucune statistique fiable ne peut être calculée "
                f"avec moins de {STAT_MIN} productions, et un modèle prédictif "
                f"nécessite au moins {MODEL_MIN} productions."
            ),
        }

    quantities = [r["quantity"] for r in prepared]
    stats = _statistics(quantities)
    slope = _trend_slope(prepared)
    trend_direction = _trend_description(slope, stats["mean"])
    baseline = _baseline_forecast(quantities)

    # ---- Enough for statistics but not for ML ----
    if n < MODEL_MIN:
        if n <= 3:
            count_phrase = f"des {n} dernières productions enregistrées"
        else:
            count_phrase = f"des {n} productions enregistrées"

        cv = (stats["std"] / stats["mean"]) if (stats["std"] and stats["mean"] > 0) else 0.0
        variation_phrase = " et de leur forte variation" if cv > 0.5 else ""

        return {
            "status": "statistical_only",
            "unit": unit,
            "history": history,
            "statistics": stats,
            "trend_per_year": round(slope, 2) if slope is not None else None,
            "trend_direction": trend_direction,
            "baseline_estimate": round(baseline, 2),
            "prediction": None,
            "prediction_period": None,
            "model_info": {
                "name": None,
                "trained": False,
                "reason": (
                    f"Only {n} records available. {MODEL_MIN} are required before "
                    f"an ML model can be evaluated on a chronological split."
                ),
            },
            "explanation": (
                f"Cette valeur correspond à la moyenne {count_phrase} "
                f"({_fmt_qty(stats['mean'])} {unit}). "
                f"En raison du nombre limité de données{variation_phrase}, elle ne "
                f"constitue pas une prévision fiable de la prochaine production. "
                f"Davantage d'historique est nécessaire pour évaluer un modèle "
                f"prédictif (au moins {MODEL_MIN} productions de ce produit avec "
                f"cette unité)."
            ),
        }

    # ---- Enough for ML: attempt with chronological validation ----
    eval_result = _train_and_evaluate(prepared)

    if eval_result is None:
        # Cannot evaluate at all (degenerate data): report statistics only.
        pred = None
        period = None
        model_name = "baseline_mean"
        trained = False
        mae = None
        baseline_mae = None
        reason = (
            "La validation chronologique n'a pas pu être effectuée sur ces "
            "données : le jeu de validation serait trop petit ou sans variation. "
            "Seules les statistiques historiques sont affichées."
        )
    elif not eval_result["use_model"]:
        # Model trained but baseline is at least as good — report baseline,
        # never present the model's output as a prediction.
        pred = None
        period = None
        model_name = "baseline_mean"
        trained = True
        mae = eval_result["model_mae"]
        baseline_mae = eval_result["baseline_mae"]
        reason = (
            f"Le modèle entraîné n'améliore pas la ligne de base sur la "
            f"validation (MAE {_fmt_qty(mae)} {unit} contre "
            f"{_fmt_qty(baseline_mae)} {unit} pour la moyenne glissante). "
            f"L'estimation statistique est affichée à la place."
        )
    else:
        pred = max(0.0, round(_predict_next(prepared, eval_result), 2))  # quantities cannot be negative
        period = _next_period(prepared)
        model_name = "gradient_boosting"
        trained = True
        mae = eval_result["model_mae"]
        baseline_mae = eval_result["baseline_mae"]
        reason = (
            f"Modèle entraîné sur {eval_result['train_size']} productions, "
            f"validé chronologiquement sur {eval_result['test_size']}. "
            f"Il améliore la ligne de base sur la validation "
            f"(MAE {_fmt_qty(mae)} {unit} contre {_fmt_qty(baseline_mae)} {unit})."
        )

    if pred is not None:
        explanation = (
            f"Prévision par apprentissage automatique pour la prochaine période "
            f"de production ({_period_label(period)}), estimée à partir des "
            f"{n} productions historiques : {_fmt_qty(pred)} {unit}. "
            + reason
        )
    else:
        explanation = (
            f"Estimation statistique : {_fmt_qty(baseline)} {unit} "
            f"(moyenne des {min(3, n)} dernières productions). " + reason
        )

    return {
        "status": "ok",
        "unit": unit,
        "history": history,
        "statistics": stats,
        "trend_per_year": round(slope, 2) if slope is not None else None,
        "trend_direction": trend_direction,
        "baseline_estimate": round(baseline, 2),
        "prediction": pred,
        "prediction_period": period,
        "model_info": {
            "name": model_name,
            "trained": trained,
            # Full precision: rounding is a display concern. The reason text and
            # the UI both format these to one decimal, and pre-rounding here
            # would make them drift apart (double rounding).
            "mae": mae,
            "baseline_mae": baseline_mae,
            "train_size": eval_result["train_size"] if eval_result else None,
            "test_size": eval_result["test_size"] if eval_result else None,
            "reason": reason,
        },
        "explanation": explanation,
    }
