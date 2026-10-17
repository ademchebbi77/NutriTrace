"""Score the LLM on the validation set (lots it never saw in training).

    python scripts/evaluate_llm.py              # fine-tuned model (base + adapter)
    python scripts/evaluate_llm.py --base       # base model alone, to measure what fine-tuning brought

A chat answer is correct when it contains every expected value ("checks": the
distance, the score, oui/non...). Questions the data cannot answer and off-topic
ones are correct when the model declines instead of inventing.
"""

import argparse
import json
import re
import sys
import time
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from nutritrace_ai.config import ADAPTER_DIR, DATASET_DIR, MODELS_DIR  # noqa: E402
from nutritrace_ai.llm import LocalLLM  # noqa: E402

REFUSAL = re.compile(r"(ne dispose pas|ne figure pas|ne précisent pas|ne publie|uniquement aux questions|pas cette information|ne peux pas)", re.I)


def normalize(text: str) -> str:
    text = text.replace(" ", " ").replace("\xa0", " ").replace("’", "'").lower()
    return re.sub(r"\s+", " ", text)


def correct(row: dict, answer: str) -> bool:
    if row["intent"] in ("unknown", "off_topic", "contact"):
        return bool(REFUSAL.search(answer))
    return all(normalize(check) in normalize(answer) for check in row["checks"])


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--base", action="store_true", help="evaluate the base model without the adapter")
    parser.add_argument("--limit", type=int, default=150)
    parser.add_argument("--show", type=int, default=8, help="examples to print")
    args = parser.parse_args()

    rows = [json.loads(line) for line in (DATASET_DIR / "val.jsonl").open(encoding="utf-8")]
    rows = [row for row in rows if row["task"] == "chat" and (row["checks"] or row["intent"] in ("unknown", "off_topic", "contact"))][: args.limit]

    llm = LocalLLM(adapter_dir=None if args.base else ADAPTER_DIR)
    print(f"Model: {llm.name} on {llm.device}, {len(rows)} validation questions")

    per_intent = defaultdict(lambda: [0, 0])
    results, started = [], time.time()
    for i, row in enumerate(rows, start=1):
        answer = llm.generate(row["messages"][:-1])
        ok = correct(row, answer)
        per_intent[row["intent"]][0] += ok
        per_intent[row["intent"]][1] += 1
        results.append({"intent": row["intent"], "question": row["messages"][1]["content"].split("QUESTION : ")[-1],
                        "expected": row["messages"][-1]["content"], "answer": answer, "correct": ok})
        if i % 25 == 0:
            print(f"  {i}/{len(rows)}...")

    accuracy = sum(r["correct"] for r in results) / len(results)
    print(f"\nAccuracy: {accuracy:.1%}  ({(time.time() - started) / len(results):.1f} s per answer)")
    for intent, (good, total) in sorted(per_intent.items(), key=lambda item: item[1][0] / item[1][1]):
        print(f"  {intent:16} {good}/{total}")
    for r in results[: args.show]:
        print(f"\n[{'OK' if r['correct'] else 'KO'}] {r['question']}\n  attendu : {r['expected']}\n  modèle  : {r['answer']}")

    out = MODELS_DIR / f"eval-{'base' if args.base else 'finetuned'}.json"
    out.write_text(json.dumps({"model": llm.name, "accuracy": accuracy, "per_intent": dict(per_intent), "results": results},
                              indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"\nDetails saved to {out}")


if __name__ == "__main__":
    main()
