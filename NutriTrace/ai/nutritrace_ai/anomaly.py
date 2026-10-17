"""Anomaly model of transports: a random forest over features.vector()."""

from pathlib import Path
from typing import Any

import joblib
import numpy as np
from sklearn.ensemble import RandomForestClassifier

from .config import ANOMALY_MODEL_PATH
from .features import FEATURE_NAMES, reasons, vector


def build_model(seed: int = 42) -> RandomForestClassifier:
    return RandomForestClassifier(n_estimators=300, min_samples_leaf=2, class_weight="balanced", n_jobs=-1, random_state=seed)


class AnomalyDetector:
    def __init__(self, model: RandomForestClassifier):
        self.model = model

    @classmethod
    def load(cls, path: Path = ANOMALY_MODEL_PATH) -> "AnomalyDetector":
        bundle = joblib.load(path)
        if bundle["features"] != FEATURE_NAMES:
            raise RuntimeError("The anomaly model was trained on other features: run scripts/train_anomaly.py again.")
        return cls(bundle["model"])

    def save(self, path: Path = ANOMALY_MODEL_PATH, metrics: dict | None = None) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        joblib.dump({"model": self.model, "features": FEATURE_NAMES, "metrics": metrics or {}}, path)

    def predict(self, transport: dict[str, Any]) -> dict[str, Any]:
        probabilities = self.model.predict_proba(np.array([vector(transport)]))[0]
        best = int(np.argmax(probabilities))
        label = str(self.model.classes_[best])
        return {
            "is_anomaly": label != "normal",
            "label": label,
            "confidence": round(float(probabilities[best]), 4),
            "reasons": reasons(transport, label),
        }
