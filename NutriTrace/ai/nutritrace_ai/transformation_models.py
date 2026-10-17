"""scikit-learn models of the Transformation - Lots module."""

import math
from pathlib import Path
from typing import Any

import joblib
import numpy as np
from sklearn.ensemble import GradientBoostingRegressor

from . import shelf_life, transformations
from .tabular import TabularClassifier


class TransformationModel(TabularClassifier):
    """Random forest over transformations.vector(): normal or the kind of anomaly."""

    feature_names = transformations.FEATURE_NAMES

    def predict(self, transformation: dict[str, Any]) -> dict[str, Any]:
        probabilities = self.probabilities(transformations.vector(transformation))
        label = max(probabilities, key=probabilities.get)
        return {
            "is_anomaly": label != "normal",
            "label": label,
            "confidence": round(probabilities[label], 4),
            "risk": transformations.risk(probabilities),
            "reasons": transformations.reasons(transformation, label),
            "expected": transformations.expected(transformation),
        }


def build_regressor(seed: int = 42) -> GradientBoostingRegressor:
    return GradientBoostingRegressor(n_estimators=250, max_depth=3, learning_rate=0.08, random_state=seed)


class ShelfLifeModel:
    """Predicts the log of the shelf life in days, from shelf_life.vector()."""

    feature_names = shelf_life.FEATURE_NAMES

    def __init__(self, model: GradientBoostingRegressor):
        self.model = model

    @classmethod
    def load(cls, path: Path) -> "ShelfLifeModel":
        bundle = joblib.load(path)
        if bundle["features"] != cls.feature_names:
            raise RuntimeError(f"{path.name} was trained on other features: train it again.")
        return cls(bundle["model"])

    def save(self, path: Path, metrics: dict | None = None) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        joblib.dump({"model": self.model, "features": self.feature_names, "metrics": metrics or {}}, path)

    def days(self, lot: dict[str, Any]) -> float:
        return float(math.exp(self.model.predict(np.array([shelf_life.vector(lot)]))[0]))

    def predict(self, lot: dict[str, Any]) -> dict[str, Any]:
        result = shelf_life.judge(lot, self.days(lot))
        return {**result, "id": lot.get("id"), "reasons": [r for r in shelf_life.reasons(lot, result) if r]}
