"""Random-forest classifier over hand-made features, saved with the list of its features.

Shared by the three scikit-learn models: transport anomalies, certificate risk and
reception check.
"""

from collections.abc import Callable
from pathlib import Path
from typing import Any

import joblib
import numpy as np
from sklearn.ensemble import RandomForestClassifier


def build_model(seed: int = 42) -> RandomForestClassifier:
    return RandomForestClassifier(n_estimators=300, min_samples_leaf=2, class_weight="balanced", n_jobs=-1, random_state=seed)


class TabularClassifier:
    feature_names: list[str] = []

    def __init__(self, model: RandomForestClassifier):
        self.model = model

    @classmethod
    def load(cls, path: Path):
        bundle = joblib.load(path)
        if bundle["features"] != cls.feature_names:
            raise RuntimeError(f"{path.name} was trained on other features: train it again.")
        return cls(bundle["model"])

    def save(self, path: Path, metrics: dict | None = None) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        joblib.dump({"model": self.model, "features": self.feature_names, "metrics": metrics or {}}, path)

    def probabilities(self, features: list[float]) -> dict[str, float]:
        values = self.model.predict_proba(np.array([features]))[0]
        return {str(label): float(p) for label, p in zip(self.model.classes_, values)}

    def best(self, features: list[float]) -> tuple[str, float]:
        probabilities = self.probabilities(features)
        label = max(probabilities, key=probabilities.get)
        return label, round(probabilities[label], 4)


def fit(cls: type[TabularClassifier], samples: list[Any], vector: Callable[[Any], list[float]], label: Callable[[Any], str], seed: int = 42):
    """Train on samples labelled by the reference rules; returns (classifier, X, y)."""
    X = np.array([vector(s) for s in samples])
    y = np.array([label(s) for s in samples])
    return cls(build_model(seed).fit(X, y)), X, y
