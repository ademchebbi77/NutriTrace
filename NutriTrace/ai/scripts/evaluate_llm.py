"""Score a module's LLM adapter on its validation set (contexts it never saw in training).

    python scripts/evaluate_llm.py                          # Transport adapter
    python scripts/evaluate_llm.py --module distribution    # Distribution - Certification adapter
    python scripts/evaluate_llm.py --base                   # base model alone, to measure what fine-tuning brought

A question is answered correctly when the answer contains every expected value ("checks":
the distance, the lot, oui/non...). Questions the data cannot answer and off-topic ones
are correct when the model declines instead of inventing. An explanation (transport
anomaly, reception, certificate) is correct when it gives the right verdict and cites the
main reason of the report.
"""

import argparse
import json
import re
import sys
import time
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from nutritrace_ai.config import MODELS_DIR, MODULES  # noqa: E402

REFUSAL = re.compile(r"(ne dispose pas|ne figure pas|ne précisent pas|ne publie|uniquement aux questions|pas cette information|ne peux pas"
                     r"|je réponds aux questions|rien inventer)", re.I)
DECLINE = {"unknown", "off_topic", "contact"}
# Words that state each verdict in the reference explanations.
VERDICTS = {
    ("reception", "accepter"): r"pouvez accepter|rien ne s'oppose",
    ("reception", "reserves"): r"précaution|demandent votre attention|réserve",
    ("reception", "refuser"): r"refuser|ne devrait pas être accepté",
    ("certificate", "faible"): r"authentique|aucune incohérence|rien ne s'oppose",
    ("certificate", "moyen"): r"points? à vérifier|plausible|vérification",
    ("certificate", "eleve"): r"suspect|douteux",
}
SCORED_TASKS = {"chat", "stock", "reception", "certificate"}


def normalize(text: str) -> str:
    text = text.replace(" ", " ").replace("\xa0", " ").replace("’", "'").lower()
    return re.sub(r"\s+", " ", text)


def correct(row: dict, answer: str) -> bool:
    if row["intent"] in DECLINE:
        return bool(REFUSAL.search(answer))
    verdict = VERDICTS.get((row["task"], row["intent"]))
    if verdict and not re.search(verdict, answer, re.I):
        return False
    return all(normalize(check) in normalize(answer) for check in row["checks"])


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--module", choices=list(MODULES), default="transport")
    parser.add_argument("--base", action="store_true", help="evaluate the base model without the adapter")
    parser.add_argument("--limit", type=int, default=150)
    parser.add_argument("--show", type=int, default=8, help="examples to print")
    args = parser.parse_args()

    from nutritrace_ai.llm import LocalLLM

    rows = [json.loads(line) for line in (MODULES[args.module]["dataset"] / "val.jsonl").open(encoding="utf-8")]
    rows = [row for row in rows if row["task"] in SCORED_TASKS and (row["checks"] or row["intent"] in DECLINE or (row["task"], row["intent"]) in VERDICTS)]
    rows = rows[: args.limit]

    llm = LocalLLM(adapters={} if args.base else {args.module: MODULES[args.module]["adapter"]})
    print(f"Model: {llm.model_name(args.module)} on {llm.device}, {len(rows)} validation examples")

    per_intent = defaultdict(lambda: [0, 0])
    per_task = defaultdict(lambda: [0, 0])
    results, started = [], time.time()
    for i, row in enumerate(rows, start=1):
        answer = llm.generate(row["messages"][:-1], module=args.module)
        ok = correct(row, answer)
        for counter in (per_intent[row["intent"]], per_task[row["task"]]):
            counter[0] += ok
            counter[1] += 1
        results.append({"task": row["task"], "intent": row["intent"], "question": row["messages"][1]["content"].split("QUESTION : ")[-1][-300:],
                        "expected": row["messages"][-1]["content"], "answer": answer, "correct": ok})
        if i % 25 == 0:
            print(f"  {i}/{len(rows)}...", flush=True)

    accuracy = sum(r["correct"] for r in results) / max(1, len(results))
    print(f"\nAccuracy: {accuracy:.1%}  ({(time.time() - started) / max(1, len(results)):.1f} s per answer)")
    for task, (good, total) in per_task.items():
        print(f"  task {task:12} {good}/{total}")
    for intent, (good, total) in sorted(per_intent.items(), key=lambda item: item[1][0] / item[1][1]):
        print(f"  {intent:18} {good}/{total}")
    for r in results[: args.show]:
        print(f"\n[{'OK' if r['correct'] else 'KO'}] {r['question']}\n  attendu : {r['expected']}\n  modèle  : {r['answer']}")

    out = MODELS_DIR / f"eval-{args.module}-{'base' if args.base else 'finetuned'}.json"
    out.write_text(json.dumps({"module": args.module, "model": llm.model_name(args.module), "accuracy": accuracy,
                               "per_task": dict(per_task), "per_intent": dict(per_intent), "results": results},
                              indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"\nDetails saved to {out}")


if __name__ == "__main__":
    main()
