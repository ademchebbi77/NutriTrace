import json
import random

import pytest

from nutritrace_ai import qa, synth
from nutritrace_ai.config import EXPORT_DIR
from nutritrace_ai.context import render, summarize
from nutritrace_ai.formatting import km
from nutritrace_ai.prompts import chat_messages


def real_lots():
    path = EXPORT_DIR / "lots.json"
    return json.loads(path.read_text(encoding="utf-8")) if path.exists() else []


def contexts():
    rng = random.Random(5)
    return [synth.lot(rng) for _ in range(150)] + real_lots()


def test_synthetic_lots_have_the_laravel_format():
    lot = synth.lot(random.Random(1))
    assert list(lot) == ["lot", "product", "journey", "source_lots", "transports", "chain", "environmental_impact",
                         "local", "certifications", "transparency", "warnings", "under_investigation"]


def test_the_context_states_the_totals_the_model_must_quote():
    for ctx in contexts():
        text = render(ctx)
        s = summarize(ctx)
        assert ctx["lot"]["number"] in text
        assert "Distance totale parcourue" in text
        assert len(text) < 6000
        if s.active:
            assert f"parcourue (hors transports annulés) : {km(s.total_km)}" in text


@pytest.mark.parametrize("intent", list(qa.INTENTS))
def test_every_answer_contains_its_checks(intent):
    """The evaluation counts an answer as correct when it holds its checks: the references must."""
    rng = random.Random(intent)
    _, build, _ = qa.INTENTS[intent]
    for ctx in contexts():
        answer, checks = build(ctx, summarize(ctx), rng)
        assert answer.strip()
        for check in checks:
            assert check in answer, (intent, check, answer)


def test_examples_mix_intents_and_build_chat_messages():
    ctx = synth.lot(random.Random(2))
    examples = qa.examples(ctx, random.Random(3), 20)
    assert len(examples) == 20
    assert len({e.intent for e in examples}) >= 10
    messages = chat_messages(ctx, examples[0].question)
    assert [m["role"] for m in messages] == ["system", "user"]
    assert "DONNÉES DU LOT" in messages[1]["content"] and examples[0].question.strip() in messages[1]["content"]
