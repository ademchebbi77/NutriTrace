"""scikit-learn models of the Distribution - Certification module."""

from typing import Any

from . import certificates, reception
from .tabular import TabularClassifier


class CertificateRiskModel(TabularClassifier):
    feature_names = certificates.FEATURE_NAMES

    def predict(self, certificate: dict[str, Any]) -> dict[str, Any]:
        probabilities = self.probabilities(certificates.vector(certificate))
        level = max(probabilities, key=probabilities.get)
        # One number for sorting: 0 = surely genuine, 1 = surely suspicious.
        risk = probabilities.get("moyen", 0) * 0.5 + probabilities.get("eleve", 0)
        return {
            "level": level,
            "risk": round(risk, 4),
            "confidence": round(probabilities[level], 4),
            "reasons": certificates.reasons(certificate, level),
        }


class ReceptionModel(TabularClassifier):
    feature_names = reception.FEATURE_NAMES

    def predict(self, payload: dict[str, Any], transport_label: str | None = None) -> dict[str, Any]:
        decision, confidence = self.best(reception.vector(payload, transport_label))
        return {
            "decision": decision,
            "confidence": confidence,
            "reasons": reception.reasons(payload, decision, transport_label),
        }
