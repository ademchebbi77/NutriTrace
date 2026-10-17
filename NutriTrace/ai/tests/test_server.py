import base64
import math
import random

import pytest
from fastapi.testclient import TestClient

import server
from nutritrace_ai import certificates, reception, synth
from nutritrace_ai import synth_distribution as dsynth
from nutritrace_ai.anomaly import AnomalyDetector
from nutritrace_ai.distribution_models import CertificateRiskModel, ReceptionModel
from nutritrace_ai import shelf_life, transformations
from nutritrace_ai import synth_transformation as tsynth
from nutritrace_ai.transformation_models import ShelfLifeModel, TransformationModel, build_regressor
from nutritrace_ai.features import LABELS, rule_label, vector
from nutritrace_ai.tabular import build_model


class FakeLLM:
    name = "fake-llm"
    fine_tuned = True
    adapters = ["transport", "distribution"]

    def __init__(self):
        self.prompts = []

    def model_name(self, module):
        return f"fake-{module}"

    def generate(self, messages, module="transport"):
        self.prompts.append((module, messages))
        return "Réponse du modèle."


def make_pdf(lines: list[str]) -> bytes:
    """One-page text PDF, as App\\Support\\SimplePdf writes the demo certificates."""
    content = "".join(f"BT /F1 12 Tf 60 {760 - 20 * i} Td ({line}) Tj ET\n" for i, line in enumerate(lines)).encode("cp1252")
    objects = [b"<< /Type /Catalog /Pages 2 0 R >>", b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
               b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
               b"<< /Length %d >>\nstream\n" % len(content) + content + b"endstream",
               b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>"]
    pdf, offsets = b"%PDF-1.4\n", []
    for number, body in enumerate(objects, start=1):
        offsets.append(len(pdf))
        pdf += b"%d 0 obj\n" % number + body + b"\nendobj\n"
    xref = len(pdf)
    pdf += b"xref\n0 %d\n0000000000 65535 f \n" % (len(objects) + 1) + b"".join(b"%010d 00000 n \n" % o for o in offsets)
    return pdf + b"trailer << /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n" % (len(objects) + 1, xref)


def tiny(cls, samples, vector_fn, label_fn):
    return cls(build_model().fit([vector_fn(s) for s in samples], [label_fn(s) for s in samples]))


@pytest.fixture(scope="module")
def models():
    rng = random.Random(0)
    lots = [tsynth.lot(rng) for _ in range(600)]
    normal = synth.normal_transports(rng, 200)
    transports = normal + [synth.inject(rng, rng.choice(normal), kind) for kind in LABELS[1:] for _ in range(40)]
    return {
        "anomaly": tiny(AnomalyDetector, transports, vector, rule_label),
        "cert": tiny(CertificateRiskModel, [dsynth.certificate(rng) for _ in range(800)], certificates.vector, certificates.rule_level),
        "reception": tiny(ReceptionModel, [dsynth.reception(rng) for _ in range(800)], reception.vector, reception.rule_decision),
        "transformation": tiny(TransformationModel, [tsynth.transformation(rng) for _ in range(800)], transformations.vector, transformations.rule_label),
        "shelf": ShelfLifeModel(build_regressor().fit([shelf_life.vector(l) for l in lots], [math.log(l["true_days"]) for l in lots])),
    }


@pytest.fixture
def client(monkeypatch, models):
    llm = FakeLLM()
    monkeypatch.setattr(server, "load_models", lambda: None)
    monkeypatch.setattr(server, "state", {"llm": llm, **models, "errors": [], "process": None})
    with TestClient(server.app) as test_client:
        test_client.llm = llm
        yield test_client


def test_health(client):
    health = client.get("/health").json()
    assert health["ready"] and health["chat"] and health["anomaly"] and health["certification"] and health["reception"]
    assert health["adapters"] == ["transport", "distribution"] and health["training"] is False


def test_chat_puts_the_lot_data_in_the_prompt(client):
    ctx = synth.lot(random.Random(4))
    response = client.post("/chat", json={"context": ctx, "question": "Quelle distance ?"})

    assert response.json() == {"answer": "Réponse du modèle.", "model": "fake-transport"}
    module, messages = client.llm.prompts[-1]
    assert module == "transport" and ctx["lot"]["number"] in messages[1]["content"]


def test_chat_rejects_a_broken_context(client):
    assert client.post("/chat", json={"context": {"lot": {}}, "question": "Quelle distance ?"}).status_code == 422
    assert client.post("/chat", json={"context": {}, "question": "a"}).status_code == 422


def test_analyze_returns_the_verdict_and_the_explanation(client):
    transport = synth.normal_transports(random.Random(9), 1)[0]
    body = client.post("/transport/analyze", json={"transport": {**transport, "distance_km": None}}).json()

    assert body["is_anomaly"] is True and body["label"] == "missing_distance"
    assert body["reasons"] and body["explanation"] == "Réponse du modèle."


def test_distributor_chat_uses_the_distribution_adapter(client):
    ctx = dsynth.distributor(random.Random(2))
    body = client.post("/distribution/chat", json={"context": ctx, "question": "Quels lots expirent cette semaine ?"}).json()

    assert body["model"] == "fake-distribution"
    module, messages = client.llm.prompts[-1]
    assert module == "distribution" and "DONNÉES DU DISTRIBUTEUR" in messages[1]["content"]


def test_reception_check_uses_the_transport_model_too(client):
    payload = dsynth.reception(random.Random(1))
    payload["lot"]["expiration_date"] = "2000-01-01"
    body = client.post("/distribution/reception", json={"reception": payload}).json()

    assert body["decision"] == "refuser" and body["reasons"] and body["explanation"] == "Réponse du modèle."
    assert ("transport_label" in body) and client.llm.prompts[-1][0] == "distribution"


def test_certificate_risk_reads_the_pdf(client):
    from nutritrace_ai.synth_distribution import certificate

    c = certificate(random.Random(3), "BIO")
    c.update(issuer="Ecocert", number="TN-BIO-2025-0412", issue_date="2025-03-01", expiration_date="2027-02-28", duplicates=0)
    pdf = make_pdf(["Ecocert", "Certificat n° TN-BIO-2025-0412", f"Délivré à : {c['owner']['organization']}", "Agriculture biologique"])
    c["document"] = {"present": True, "mime": "application/pdf", "base64": base64.b64encode(pdf).decode()}

    body = client.post("/certification/risk", json={"certificate": c, "explain": True}).json()
    assert body["document_readable"] is True and body["level"] in certificates.LEVELS
    assert 0 <= body["risk"] <= 1 and body["explanation"] == "Réponse du modèle."

    c["issuer"] = "Ecocerts"
    c["document"] = {"present": False}
    batch = client.post("/certification/risk/batch", json={"certificates": [c]}).json()["results"]
    assert batch[0]["level"] == "eleve" and batch[0]["explanation"] is None
    assert any("ressemble à « Ecocert »" in reason for reason in batch[0]["reasons"])


def test_the_token_is_checked_when_configured(client, monkeypatch):
    monkeypatch.setattr(server, "SERVICE_TOKEN", "secret")
    ctx = synth.lot(random.Random(4))
    assert client.post("/chat", json={"context": ctx, "question": "Quelle distance ?"}).status_code == 401
    assert client.post("/chat", json={"context": ctx, "question": "Quelle distance ?"}, headers={"X-AI-Token": "secret"}).status_code == 200


def test_without_llm_scores_still_work_with_template_explanations(client, monkeypatch):
    monkeypatch.setitem(server.state, "llm", None)

    assert client.post("/chat", json={"context": synth.lot(random.Random(1)), "question": "Quelle distance ?"}).status_code == 503
    body = client.post("/transport/analyze", json={"transport": synth.normal_transports(random.Random(9), 1)[0]}).json()
    assert body["label"] == "normal" and body["explanation"] and body["model"] == "random-forest"
    body = client.post("/distribution/reception", json={"reception": dsynth.reception(random.Random(5))}).json()
    assert body["decision"] in reception.DECISIONS and body["explanation"]


def test_training_status_and_refusals(client, monkeypatch):
    assert client.get("/train/status").json()["running"] is False
    assert client.post("/train", json={"module": "unknown"}).status_code == 422
    monkeypatch.setattr(server, "checkpoint_of", lambda module: None)
    assert client.post("/train", json={"module": "distribution", "resume": True}).status_code == 409
    assert client.post("/train/cancel").status_code == 409
    models = client.get("/models").json()
    assert set(models["classifiers"]) == {"anomaly", "cert", "reception", "transformation", "shelf"}
    assert {a["module"] for a in models["adapters"]} == {"transport", "distribution", "transformation"}


def test_transformation_check_for_the_transformer_and_the_admin(client):
    payload = tsynth.transformation(random.Random(4), "yield_too_high")
    body = client.post("/transformation/check", json={"transformation": payload, "explain": True, "audience": "transformer"}).json()
    assert body["label"] == "yield_too_high" and body["is_anomaly"] and body["risk"] > 0.5
    assert body["expected"]["output_max"] > 0 and body["explanation"] == "Réponse du modèle."
    module, messages = client.llm.prompts[-1]
    assert module == "transformation" and "le transformateur, avant enregistrement" in messages[1]["content"]

    batch = client.post("/transformation/check/batch", json={"transformations": [payload]}).json()["results"]
    assert batch[0]["label"] == "yield_too_high" and batch[0]["explanation"] is None
    assert client.post("/transformation/check", json={"transformation": payload, "audience": "consumer"}).status_code == 422


def test_lots_expiry_batch(client):
    lots = [{"id": 1, "product": "Fromage Sicilien de Béja", "category": "Produits laitiers", "unit": "kg", "production_date": "2026-01-01", "expiration_date": "2028-01-01"},
            {"id": 2, "product": "Miel de thym", "category": "Miels", "unit": "kg", "production_date": "2026-01-01", "expiration_date": None}]
    results = client.post("/lots/expiry/batch", json={"lots": lots}).json()["results"]
    assert [r["label"] for r in results] == ["too_long", "missing"]
    assert results[1]["suggested_date"] > "2026-12-01"
