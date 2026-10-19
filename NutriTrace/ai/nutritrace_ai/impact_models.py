"""scikit-learn models of the Impact environnemental - Avis et signalements module."""

from pathlib import Path
from typing import Any

import joblib
import numpy as np
from sklearn.pipeline import Pipeline

from . import impact_check, moderation
from .tabular import TabularClassifier


class ImpactCheckModel(TabularClassifier):
    """Random forest over impact_check.vector(): one decision per declared figure."""

    feature_names = impact_check.FEATURE_NAMES

    def predict(self, declaration: dict[str, Any]) -> dict[str, Any]:
        rows = impact_check.rows(declaration)
        if not rows:
            return {"is_anomaly": False, "label": "ok", "confidence": 1.0, "risk": 0.0, "findings": [], "reasons": []}

        values = self.model.predict_proba(np.array([impact_check.vector(row) for row in rows]))
        classes = [str(c) for c in self.model.classes_]
        results, findings = [], []
        for row, probabilities in zip(rows, values):
            label = classes[int(np.argmax(probabilities))]
            results.append((row, label))
            findings.append({"indicator": row["indicator"], "label": label, "confidence": round(float(max(probabilities)), 4),
                             "reason": impact_check.reason(row, label)})

        label = impact_check.overall([label for _, label in results])
        worst = [f for f in findings if f["label"] == label]
        confidence = max(f["confidence"] for f in worst)
        return {
            "is_anomaly": label != "ok",
            "label": label,
            "confidence": confidence,
            "risk": 0.0 if label == "ok" else round(confidence, 4),
            "findings": findings,
            "reasons": [f["reason"] for f in findings if f["label"] != "ok"] or ["Toutes les valeurs déclarées sont plausibles."],
            "_results": results,
        }


class ModerationModel:
    """TF-IDF + logistic regression on review comments, completed by explicit rules."""

    def __init__(self, pipeline: Pipeline):
        self.pipeline = pipeline

    @classmethod
    def load(cls, path: Path) -> "ModerationModel":
        bundle = joblib.load(path)
        return cls(bundle["model"])

    def save(self, path: Path, metrics: dict | None = None) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        joblib.dump({"model": self.pipeline, "features": "tfidf", "metrics": metrics or {}}, path)

    def predict(self, review: dict[str, Any]) -> dict[str, Any]:
        return {**moderation.judge(self.pipeline, review), "id": review.get("id")}
