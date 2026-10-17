"""Generate the fine-tuning dataset of the LLM (chat format, JSON lines).

    python scripts/generate_dataset.py [--synthetic 700] [--per-real 20] [--explain 400]

Two tasks, the ones the service performs:
- "chat": a question about a lot, its data in the prompt, the answer computed from that data;
- "explain": an anomaly report of a transport, and the explanation to write.

Lots are split between train and validation (not questions), so the validation set
measures how the model does on lots it has never seen.
"""

import argparse
import json
import random
import sys
from collections import Counter
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from nutritrace_ai import qa, synth  # noqa: E402
from nutritrace_ai.config import DATASET_DIR, EXPORT_DIR  # noqa: E402
from nutritrace_ai.explain import explain  # noqa: E402
from nutritrace_ai.features import LABELS, report, rule_label  # noqa: E402
from nutritrace_ai.prompts import chat_messages, explain_messages  # noqa: E402


def chat_rows(lots: list[dict], per_lot: int, rng: random.Random, source: str) -> list[dict]:
    rows = []
    for ctx in lots:
        for example in qa.examples(ctx, rng, per_lot):
            messages = chat_messages(ctx, example.question) + [{"role": "assistant", "content": example.answer}]
            rows.append({"task": "chat", "intent": example.intent, "source": source, "checks": example.checks, "messages": messages})
    return rows


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


def write(path: Path, rows: list[dict]) -> None:
    with path.open("w", encoding="utf-8") as handle:
        for row in rows:
            handle.write(json.dumps(row, ensure_ascii=False) + "\n")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--synthetic", type=int, default=700, help="synthetic lots")
    parser.add_argument("--per-synthetic", type=int, default=3, help="questions per synthetic lot")
    parser.add_argument("--per-real", type=int, default=20, help="questions per real NutriTrace lot")
    parser.add_argument("--explain", type=int, default=400, help="anomaly explanation examples")
    parser.add_argument("--val-share", type=float, default=0.08, help="share of lots kept for validation")
    parser.add_argument("--seed", type=int, default=7)
    args = parser.parse_args()
    rng = random.Random(args.seed)

    lots_file, transports_file = EXPORT_DIR / "lots.json", EXPORT_DIR / "transports.json"
    real = json.loads(lots_file.read_text(encoding="utf-8")) if lots_file.exists() else []
    real_transports = json.loads(transports_file.read_text(encoding="utf-8")) if transports_file.exists() else []
    print(f"Real lots exported from NutriTrace: {len(real)}" + ("" if real else " (run php artisan ai:export first)"))

    synthetic = [synth.lot(rng) for _ in range(args.synthetic)]
    rng.shuffle(real)
    rng.shuffle(synthetic)
    real_cut, synth_cut = int(len(real) * args.val_share), int(len(synthetic) * args.val_share)

    train = chat_rows(real[real_cut:], args.per_real, rng, "real") + chat_rows(synthetic[synth_cut:], args.per_synthetic, rng, "synthetic")
    val = chat_rows(real[:real_cut], args.per_real // 2, rng, "real") + chat_rows(synthetic[:synth_cut], args.per_synthetic, rng, "synthetic")
    explanations = explain_rows(args.explain, real_transports, rng)
    explain_cut = int(len(explanations) * args.val_share)
    train += explanations[explain_cut:]
    val += explanations[:explain_cut]
    rng.shuffle(train)

    DATASET_DIR.mkdir(parents=True, exist_ok=True)
    write(DATASET_DIR / "train.jsonl", train)
    write(DATASET_DIR / "val.jsonl", val)

    print(f"train: {len(train)} examples, validation: {len(val)} examples -> {DATASET_DIR}")
    print("Train by intent:", dict(Counter(row["intent"] for row in train).most_common()))


if __name__ == "__main__":
    main()
