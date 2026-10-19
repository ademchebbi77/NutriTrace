"""Generate the fine-tuning dataset of a module's LoRA adapter (chat format, JSON lines).

    python scripts/generate_dataset.py                          # Transport - Traçabilité
    python scripts/generate_dataset.py --module distribution    # Distribution - Certification

Transport, two tasks:
- "chat": a question about a lot, its data in the prompt, the answer computed from that data;
- "explain": an anomaly report of a transport, and the explanation to write.

Distribution - Certification, three tasks:
- "stock": a distributor's question, its stock and sales in the prompt, the computed answer;
- "reception": a reception check report, and the advice to write;
- "certificate": a certificate risk report, and the explanation for the admin.

Contexts (lots, distributors) are split between train and validation, not questions, so the
validation set measures how the model does on data it has never seen.
"""

import argparse
import json
import random
import sys
from collections import Counter
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from nutritrace_ai import advisor, certificates, impact_check, moderation, qa, reception, stock_qa, synth, transformations  # noqa: E402
from nutritrace_ai import synth_impact as isynth  # noqa: E402
from nutritrace_ai import synth_distribution as dsynth  # noqa: E402
from nutritrace_ai import synth_transformation as tsynth  # noqa: E402
from nutritrace_ai.config import EXPORT_DIR, MODULES  # noqa: E402
from nutritrace_ai.explain import explain  # noqa: E402
from nutritrace_ai.features import LABELS, report, rule_label  # noqa: E402
from nutritrace_ai.pdf_text import extract  # noqa: E402
from nutritrace_ai.prompts import (certificate_messages, chat_messages, explain_messages, reception_messages,  # noqa: E402
                                   impact_messages, stock_messages, transformation_messages)


def load(name: str) -> list[dict]:
    path = EXPORT_DIR / name
    return json.loads(path.read_text(encoding="utf-8")) if path.exists() else []


def split(items: list, share: float, rng: random.Random) -> tuple[list, list]:
    items = list(items)
    rng.shuffle(items)
    cut = int(len(items) * share)
    return items[cut:], items[:cut]


def qa_rows(contexts: list[dict], per_context: int, rng: random.Random, source: str, task: str) -> list[dict]:
    generate, messages = (qa.examples, chat_messages) if task == "chat" else (stock_qa.examples, stock_messages)
    rows = []
    for ctx in contexts:
        for example in generate(ctx, rng, per_context):
            rows.append({"task": task, "intent": example.intent, "source": source, "checks": example.checks,
                         "messages": messages(ctx, example.question) + [{"role": "assistant", "content": example.answer}]})
    return rows


# --- transport ------------------------------------------------------------------------------

def explain_rows(count: int, real: list[dict], rng: random.Random) -> list[dict]:
    normal = [t for t in real if t.get("distance_km") is not None] + synth.normal_transports(rng, count)
    rows = []
    for i in range(count):
        kind = LABELS[i % len(LABELS)]
        transport = rng.choice(normal) if kind == "normal" else synth.inject(rng, rng.choice(normal), kind)
        label = rule_label(transport)
        confidence = rng.uniform(0.75, 1.0)
        messages = explain_messages(report(transport, label, confidence)) + [{"role": "assistant", "content": explain(transport, label, rng)}]
        rows.append({"task": "explain", "intent": label, "source": "synthetic", "checks": [], "messages": messages})
    return rows


def transport(args, rng) -> tuple[list[dict], list[dict]]:
    real = load("lots.json")
    print(f"Real lots exported from NutriTrace: {len(real)}" + ("" if real else " (run php artisan ai:export first)"))
    real_train, real_val = split(real, args.val_share, rng)
    synth_train, synth_val = split([synth.lot(rng) for _ in range(args.synthetic or 700)], args.val_share, rng)

    train = qa_rows(real_train, args.per_real or 20, rng, "real", "chat") + qa_rows(synth_train, args.per_synthetic, rng, "synthetic", "chat")
    val = qa_rows(real_val, (args.per_real or 20) // 2, rng, "real", "chat") + qa_rows(synth_val, args.per_synthetic, rng, "synthetic", "chat")
    explanations_train, explanations_val = split(explain_rows(args.explain, load("transports.json"), rng), args.val_share, rng)
    return train + explanations_train, val + explanations_val


# --- distribution - certification -----------------------------------------------------------

def reception_rows(payloads: list[dict], rng: random.Random, source: str) -> list[dict]:
    rows = []
    for payload in payloads:
        decision = reception.rule_decision(payload)
        messages = reception_messages(reception.report(payload, decision, rng.uniform(0.7, 1.0)))
        first = reception.reasons(payload, decision)[:1]
        rows.append({"task": "reception", "intent": decision, "source": source, "checks": [r[:40] for r in first],
                     "messages": messages + [{"role": "assistant", "content": reception.explain(payload, decision, rng)}]})
    return rows


def certificate_rows(payloads: list[dict], rng: random.Random, source: str) -> list[dict]:
    rows = []
    for c in payloads:
        level = certificates.rule_level(c)
        risk = {"faible": rng.uniform(0.0, 0.25), "moyen": rng.uniform(0.35, 0.65), "eleve": rng.uniform(0.7, 1.0)}[level]
        messages = certificate_messages(certificates.report(c, level, risk))
        first = certificates.reasons(c, level)[:1]
        rows.append({"task": "certificate", "intent": level, "source": source, "checks": [r[:40] for r in first],
                     "messages": messages + [{"role": "assistant", "content": certificates.explain(c, level, rng)}]})
    return rows


def distribution(args, rng) -> tuple[list[dict], list[dict]]:
    real_distributors, real_receptions, real_certs = load("distributors.json"), load("receptions.json"), load("certifications.json")
    for c in real_certs:
        if (c.get("document") or {}).get("present"):
            c["document"] = {**c["document"], "text": extract(c["document"])}
    print(f"Real data exported from NutriTrace: {len(real_distributors)} distributors, {len(real_receptions)} receptions,"
          f" {len(real_certs)} certificates" + ("" if real_distributors else " (run php artisan ai:export first)"))

    real_train, real_val = split(real_distributors, max(args.val_share, 0.2) if len(real_distributors) >= 5 else 0, rng)
    synth_train, synth_val = split([dsynth.distributor(rng) for _ in range(args.synthetic or 650)], args.val_share, rng)
    train = qa_rows(real_train, args.per_real or 40, rng, "real", "stock") + qa_rows(synth_train, args.per_synthetic, rng, "synthetic", "stock")
    val = qa_rows(real_val, (args.per_real or 40) // 2, rng, "real", "stock") + qa_rows(synth_val, args.per_synthetic, rng, "synthetic", "stock")

    receptions = reception_rows(real_receptions, rng, "real") + reception_rows([dsynth.reception(rng) for _ in range(args.explain)], rng, "synthetic")
    certs = certificate_rows(real_certs, rng, "real") + certificate_rows([dsynth.certificate(rng) for _ in range(args.explain)], rng, "synthetic")
    for rows in (receptions, certs):
        rows_train, rows_val = split(rows, args.val_share, rng)
        train += rows_train
        val += rows_val
    return train, val


# --- transformation - lots ------------------------------------------------------------------

def transformation_rows(payloads: list[dict], rng: random.Random, source: str) -> list[dict]:
    rows = []
    for payload in payloads:
        label = transformations.rule_label(payload)
        # The same check is explained to the transformer (before saving) or to the admin.
        audience = rng.choice(["transformer", "transformer", "admin"])
        messages = transformation_messages(transformations.report(payload, label, rng.uniform(0.75, 1.0), audience), audience)
        first = transformations.reasons(payload, label)[:1]
        rows.append({"task": "transformation", "intent": label, "audience": audience, "source": source, "checks": [r[:40] for r in first],
                     "messages": messages + [{"role": "assistant", "content": transformations.explain(payload, label, audience, rng)}]})
    return rows


def transformation(args, rng) -> tuple[list[dict], list[dict]]:
    real = load("transformations.json")
    print(f"Real transformations exported from NutriTrace: {len(real)}" + ("" if real else " (run php artisan ai:export first)"))
    payloads = real * 3 + [tsynth.transformation(rng) for _ in range(args.synthetic or 1500)]
    return split(transformation_rows(payloads, rng, "mixed"), args.val_share, rng)


# --- impact environnemental - avis et signalements ------------------------------------------

def impact(args, rng) -> tuple[list[dict], list[dict]]:
    declarations, cases, reviews = load("declarations.json"), load("advice.json"), load("reviews.json")
    print(f"Real data exported from NutriTrace: {len(declarations)} declarations, {len(cases)} lots, {len(reviews)} reviews"
          + ("" if cases else " (run php artisan ai:export first)"))
    rows = []

    for d in declarations * 3 + [isynth.declaration(rng) for _ in range(args.explain)]:
        results = [(row, impact_check.rule_label(row)) for row in impact_check.rows(d)]
        if not results:
            continue
        label = impact_check.overall([l for _, l in results])
        first = [impact_check.reason(r, l) for r, l in results if l != "ok"][:1]
        rows.append({"task": "impact_check", "intent": label, "source": "mixed", "checks": [r[:40] for r in first],
                     "messages": impact_messages(impact_check.report(d, results, label, rng.uniform(0.75, 1.0)), "check")
                     + [{"role": "assistant", "content": impact_check.explain(results, label, rng)}]})

    for case in cases * 2 + [isynth.advice_case(rng) for _ in range(args.explain)]:
        result = advisor.analyse(case)
        tips = advisor.advice(case)
        rows.append({"task": "advice", "intent": "advice" if result["score"] is not None else "no_data", "source": "mixed",
                     "checks": [tips[0][:40]] if tips else [],
                     "messages": impact_messages(advisor.report(case), "advice") + [{"role": "assistant", "content": advisor.explain(case, rng)}]})

    for review in reviews + [isynth.review(rng) for _ in range(args.explain)]:
        result = moderation.judge(None, review) if review.get("kind") is None else _labelled(review)
        rows.append({"task": "moderation", "intent": result["label"], "source": "mixed", "checks": [result["reasons"][0][:40]],
                     "messages": impact_messages(moderation.report(review, result), "moderation")
                     + [{"role": "assistant", "content": moderation.explain(review, result, rng)}]})

    return split(rows, args.val_share, rng)


def _labelled(review: dict) -> dict:
    """Synthetic reviews carry their true label: the explanation is trained on it, not on a model's guess."""
    found = moderation.rule_findings(review["comment"])
    label = review["kind"]
    reasons = [r for l, r in found if l == label] or (
        [f"Le modèle reconnaît un texte de type « {moderation.LABEL_TEXT[label]} »."] if label != "ok"
        else ["Avis conforme : il porte sur le produit, sans propos injurieux ni données personnelles."])
    return {"label": label, "confidence": 0.95, "flagged": label != "ok", "reasons": reasons}


def write(path: Path, rows: list[dict]) -> None:
    with path.open("w", encoding="utf-8") as handle:
        for row in rows:
            handle.write(json.dumps(row, ensure_ascii=False) + "\n")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--module", choices=list(MODULES), default="transport")
    parser.add_argument("--synthetic", type=int, help="synthetic lots or distributors (default 700 / 650)")
    parser.add_argument("--per-synthetic", type=int, default=3, help="questions per synthetic context")
    parser.add_argument("--per-real", type=int, help="questions per real context (default 20 / 40)")
    parser.add_argument("--explain", type=int, default=400, help="examples of each explanation task")
    parser.add_argument("--val-share", type=float, default=0.08, help="share of contexts kept for validation")
    parser.add_argument("--seed", type=int, default=7)
    args = parser.parse_args()
    rng = random.Random(args.seed)

    train, val = {"transport": transport, "distribution": distribution, "transformation": transformation, "impact": impact}[args.module](args, rng)
    rng.shuffle(train)  # NOSONAR: seeded generator for reproducible synthetic data, not security

    folder = MODULES[args.module]["dataset"]
    folder.mkdir(parents=True, exist_ok=True)
    write(folder / "train.jsonl", train)
    write(folder / "val.jsonl", val)

    print(f"train: {len(train)} examples, validation: {len(val)} examples -> {folder}")
    print("Train by task:", dict(Counter(row["task"] for row in train)))
    print("Train by intent:", dict(Counter(row["intent"] for row in train).most_common()))


if __name__ == "__main__":
    main()
