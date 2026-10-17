import random

import pytest
from fastapi.testclient import TestClient

import server
from nutritrace_ai import synth
from nutritrace_ai.anomaly import AnomalyDetector, build_model
from nutritrace_ai.features import LABELS, rule_label, vector


class FakeLLM:
    name = "fake-llm"
    fine_tuned = True

    def __init__(self):
        self.prompts = []

    def generate(self, messages):
        self.prompts.append(messages)
        return "Réponse du modèle."


@pytest.fixture
def client(monkeypatch):
    rng = random.Random(0)
    normal = synth.normal_transports(rng, 200)
    samples = normal + [synth.inject(rng, rng.choice(normal), kind) for kind in LABELS[1:] for _ in range(40)]
    detector = AnomalyDetector(build_model().fit([vector(t) for t in samples], [rule_label(t) for t in samples]))

    llm = FakeLLM()
    monkeypatch.setattr(server, "load_models", lambda: None)
    monkeypatch.setattr(server, "state", {"llm": llm, "anomaly": detector, "errors": []})
    with TestClient(server.app) as test_client:
        test_client.llm = llm
        yield test_client


def test_health(client):
    assert client.get("/health").json() == {"ready": True, "chat": True, "anomaly": True, "model": "fake-llm", "fine_tuned": True, "errors": []}


def test_chat_puts_the_lot_data_in_the_prompt(client):
    ctx = synth.lot(random.Random(4))
    response = client.post("/chat", json={"context": ctx, "question": "Quelle distance ?"})

    assert response.status_code == 200
    assert response.json() == {"answer": "Réponse du modèle.", "model": "fake-llm"}
    prompt = client.llm.prompts[-1][1]["content"]
    assert ctx["lot"]["number"] in prompt and "QUESTION : Quelle distance ?" in prompt


def test_chat_rejects_a_broken_context(client):
    assert client.post("/chat", json={"context": {"lot": {}}, "question": "Quelle distance ?"}).status_code == 422
    assert client.post("/chat", json={"context": {}, "question": "a"}).status_code == 422


def test_analyze_returns_the_verdict_and_the_explanation(client):
    transport = synth.normal_transports(random.Random(9), 1)[0]
    body = client.post("/transport/analyze", json={"transport": {**transport, "distance_km": None}}).json()

    assert body["is_anomaly"] is True and body["label"] == "missing_distance"
    assert body["reasons"] and body["explanation"] == "Réponse du modèle."
    assert "RAPPORT D'ANALYSE" in client.llm.prompts[-1][1]["content"]


def test_the_token_is_checked_when_configured(client, monkeypatch):
    monkeypatch.setattr(server, "SERVICE_TOKEN", "secret")
    ctx = synth.lot(random.Random(4))
    assert client.post("/chat", json={"context": ctx, "question": "Quelle distance ?"}).status_code == 401
    assert client.post("/chat", json={"context": ctx, "question": "Quelle distance ?"}, headers={"X-AI-Token": "secret"}).status_code == 200


def test_without_llm_the_analysis_uses_template_explanations(client, monkeypatch):
    monkeypatch.setitem(server.state, "llm", None)
    transport = synth.normal_transports(random.Random(9), 1)[0]

    assert client.post("/chat", json={"context": synth.lot(random.Random(1)), "question": "Quelle distance ?"}).status_code == 503
    body = client.post("/transport/analyze", json={"transport": transport}).json()
    assert body["label"] == "normal" and body["explanation"] and body["model"] == "random-forest"
