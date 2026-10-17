"""Anomaly model of transports: a random forest over features.vector()."""

from pathlib import Path
from typing import Any

from .config import ANOMALY_MODEL_PATH
from .features import FEATURE_NAMES, reasons, vector
from .tabular import TabularClassifier, build_model  # noqa: F401 - build_model is used by the scripts and tests


class AnomalyDetector(TabularClassifier):
    feature_names = FEATURE_NAMES

    @classmethod
    def load(cls, path: Path = ANOMALY_MODEL_PATH) -> "AnomalyDetector":
        return super().load(path)

    def save(self, path: Path = ANOMALY_MODEL_PATH, metrics: dict | None = None) -> None:
        super().save(path, metrics)

    def predict(self, transport: dict[str, Any]) -> dict[str, Any]:
        label, confidence = self.best(vector(transport))
        return {
            "is_anomaly": label != "normal",
            "label": label,
            "confidence": confidence,
            "reasons": reasons(transport, label),
        }
