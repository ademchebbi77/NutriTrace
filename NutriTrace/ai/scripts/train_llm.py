"""Fine-tune Qwen2.5-Instruct on a module's dataset with LoRA (PyTorch + transformers + peft).

    python scripts/train_llm.py                          # Transport adapter (Qwen2.5-1.5B, 0.5B if out of GPU memory)
    python scripts/train_llm.py --module distribution    # Distribution - Certification adapter
    python scripts/train_llm.py --limit 200 --epochs 1   # quick smoke test
    python scripts/train_llm.py --qlora                  # 4-bit base model (needs bitsandbytes), for small GPUs
    python scripts/train_llm.py --resume                 # continue an interrupted run

Only the answer tokens are learned (the prompt is masked), so the model learns to
answer from the data it is given, not to reproduce the data. Each module gets its own
adapter (a few dozen MB, see config.MODULES); all are trained on the same base model so
the service can load it once and switch adapters. The base model is downloaded once
from Hugging Face and then used offline.
"""

import argparse
import gc
import json
import sys
import time
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import torch  # noqa: E402
from peft import LoraConfig, get_peft_model, prepare_model_for_kbit_training  # noqa: E402
from transformers import AutoModelForCausalLM, AutoTokenizer, Trainer, TrainingArguments  # noqa: E402

from nutritrace_ai.config import BASE_MODEL, FALLBACK_MODEL, MODELS_DIR, MODULES  # noqa: E402

LORA_TARGETS = ["q_proj", "k_proj", "v_proj", "o_proj", "gate_proj", "up_proj", "down_proj"]


def tokenize(path: Path, tokenizer, max_len: int, limit: int | None) -> tuple[list[dict], int]:
    """Prompt tokens are labelled -100 so that only the answer is learned."""
    rows = [json.loads(line) for line in path.open(encoding="utf-8")]
    if limit:
        rows = rows[:limit]
    examples, skipped = [], 0
    for row in rows:
        messages = row["messages"]
        prompt = tokenizer.apply_chat_template(messages[:-1], tokenize=False, add_generation_prompt=True)
        full = tokenizer.apply_chat_template(messages, tokenize=False)
        # The completion ends with the end-of-turn token: the model learns when to stop.
        completion = full[len(prompt):] if full.startswith(prompt) else messages[-1]["content"] + tokenizer.eos_token
        prompt_ids = tokenizer(prompt, add_special_tokens=False)["input_ids"]
        answer_ids = tokenizer(completion, add_special_tokens=False)["input_ids"]
        if len(prompt_ids) + len(answer_ids) > max_len:
            skipped += 1
            continue
        examples.append({"input_ids": prompt_ids + answer_ids, "labels": [-100] * len(prompt_ids) + answer_ids})
    return examples, skipped


class Collator:
    """Pads on the left, so that in every row the answer ends the sequence (see AnswerOnlyTrainer)."""

    def __init__(self, pad_id: int):
        self.pad_id = pad_id

    def __call__(self, batch: list[dict]) -> dict[str, torch.Tensor]:
        longest = max(len(item["input_ids"]) for item in batch)
        pad = lambda seq, value: [value] * (longest - len(seq)) + seq  # noqa: E731
        mask = torch.tensor([pad([1] * len(item["input_ids"]), 0) for item in batch])
        return {
            "input_ids": torch.tensor([pad(item["input_ids"], self.pad_id) for item in batch]),
            "labels": torch.tensor([pad(item["labels"], -100) for item in batch]),
            "attention_mask": mask,
            "position_ids": (mask.cumsum(dim=1) - 1).clamp(min=0),
        }


class AnswerOnlyTrainer(Trainer):
    """Computes the vocabulary scores for the answer tokens only.

    Qwen2.5 has a 152,000-word vocabulary: the scores of a 1,500-token prompt would take
    more than the free memory of a 6 GB card (Windows then silently spills into system RAM,
    20 times slower). The prompt is masked from the loss anyway, so nothing is lost.
    """

    def compute_loss(self, model, inputs, return_outputs=False, num_items_in_batch=None):
        labels = inputs.pop("labels")
        # Answers end every row (left padding): keep the last positions only, +1 for the shift.
        keep = int((labels != -100).sum(dim=1).max()) + 1
        outputs = model(**inputs, logits_to_keep=keep)
        logits = outputs.logits[:, :-1].float()
        targets = labels[:, -(keep - 1):]
        loss = torch.nn.functional.cross_entropy(logits.reshape(-1, logits.size(-1)), targets.reshape(-1), ignore_index=-100, reduction="sum")
        loss = loss / (num_items_in_batch if num_items_in_batch is not None else (targets != -100).sum())
        return (loss, outputs) if return_outputs else loss


def train(base_model: str, args: argparse.Namespace, callbacks: list | None = None) -> dict:
    started = time.time()
    dataset, adapter = MODULES[args.module]["dataset"], MODULES[args.module]["adapter"]
    checkpoints = MODELS_DIR / "checkpoints" / args.module
    print(f"\n=== Fine-tuning {base_model} for {MODULES[args.module]['label']} ===")
    tokenizer = AutoTokenizer.from_pretrained(base_model)
    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    train_set, skipped = tokenize(dataset / "train.jsonl", tokenizer, args.max_len, args.limit)
    val_set, _ = tokenize(dataset / "val.jsonl", tokenizer, args.max_len, args.limit and max(20, args.limit // 10))
    print(f"Examples: {len(train_set)} train, {len(val_set)} validation ({skipped} too long, skipped)")

    bf16 = torch.cuda.is_available() and torch.cuda.is_bf16_supported()
    load = {"dtype": torch.bfloat16 if bf16 else torch.float32}
    if torch.cuda.is_available():
        load["device_map"] = {"": 0}
    if args.qlora:
        from transformers import BitsAndBytesConfig

        load["quantization_config"] = BitsAndBytesConfig(load_in_4bit=True, bnb_4bit_quant_type="nf4",
                                                         bnb_4bit_compute_dtype=load["dtype"], bnb_4bit_use_double_quant=True)
    model = AutoModelForCausalLM.from_pretrained(base_model, **load)
    model.config.use_cache = False
    if args.qlora:
        model = prepare_model_for_kbit_training(model, use_gradient_checkpointing=True)
    model = get_peft_model(model, LoraConfig(r=args.lora_r, lora_alpha=args.lora_r * 2, lora_dropout=0.05,
                                             target_modules=LORA_TARGETS, task_type="CAUSAL_LM"))
    model.print_trainable_parameters()

    steps = max(1, int(len(train_set) * args.epochs) // (args.batch * args.grad_accum))
    trainer = AnswerOnlyTrainer(
        model=model,
        args=TrainingArguments(
            output_dir=str(checkpoints),
            per_device_train_batch_size=args.batch,
            per_device_eval_batch_size=args.batch,
            gradient_accumulation_steps=args.grad_accum,
            num_train_epochs=args.epochs,
            learning_rate=args.lr,
            lr_scheduler_type="cosine",
            warmup_steps=max(1, steps // 30),
            logging_steps=10,
            eval_strategy="steps",
            eval_steps=max(10, steps // 4),
            # A checkpoint at each evaluation: an interrupted run continues with --resume.
            save_strategy="steps",
            save_steps=max(10, steps // 4),
            save_total_limit=1,
            bf16=bf16,
            gradient_checkpointing=True,
            gradient_checkpointing_kwargs={"use_reentrant": False},
            dataloader_num_workers=0,
            remove_unused_columns=False,
            prediction_loss_only=True,
            report_to="none",
        ),
        train_dataset=train_set,
        eval_dataset=val_set,
        data_collator=Collator(tokenizer.pad_token_id),
        callbacks=callbacks,
    )
    resume = args.resume and checkpoints.exists() and any(checkpoints.glob("checkpoint-*"))
    result = trainer.train(resume_from_checkpoint=True if resume else None)

    # Saved before the final evaluation: an interruption there must not lose the training.
    adapter.mkdir(parents=True, exist_ok=True)
    model.save_pretrained(adapter)
    tokenizer.save_pretrained(adapter)
    evaluation = trainer.evaluate()

    info = {
        "base_model": base_model,
        "module": args.module,
        "trained_at": datetime.now().isoformat(timespec="seconds"),
        "train_examples": len(train_set),
        "val_examples": len(val_set),
        "epochs": args.epochs,
        "learning_rate": args.lr,
        "lora_r": args.lora_r,
        "qlora": args.qlora,
        "train_loss": round(result.training_loss, 4),
        "eval_loss": round(evaluation["eval_loss"], 4),
        "minutes": round((time.time() - started) / 60, 1),
        "log": [entry for entry in trainer.state.log_history if "loss" in entry or "eval_loss" in entry],
    }
    (adapter / "training_info.json").write_text(json.dumps(info, indent=2), encoding="utf-8")
    print(f"\nAdapter saved to {adapter} (train loss {info['train_loss']}, validation loss {info['eval_loss']}, {info['minutes']} min)")
    return info


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--module", choices=list(MODULES), default="transport")
    parser.add_argument("--base-model", default=BASE_MODEL)
    parser.add_argument("--epochs", type=float, default=1)
    parser.add_argument("--lr", type=float, default=2e-4)
    parser.add_argument("--batch", type=int, default=1)
    parser.add_argument("--grad-accum", type=int, default=16)
    parser.add_argument("--max-len", type=int, default=2560)
    parser.add_argument("--lora-r", type=int, default=16)
    parser.add_argument("--qlora", action="store_true", help="load the base model in 4 bits (bitsandbytes)")
    parser.add_argument("--limit", type=int, help="use only the first N examples (smoke test)")
    parser.add_argument("--resume", action="store_true", help="continue an interrupted run from its last checkpoint")
    parser.add_argument("--no-fallback", action="store_true", help=f"do not retry with {FALLBACK_MODEL} when out of memory")
    return parser


def run(args: argparse.Namespace, callbacks: list | None = None) -> dict:
    if not (MODULES[args.module]["dataset"] / "train.jsonl").exists():
        sys.exit(f"No dataset: run scripts/generate_dataset.py --module {args.module} first.")
    if not torch.cuda.is_available():
        print("WARNING: no CUDA GPU found, training on CPU will take many hours.")

    try:
        return train(args.base_model, args, callbacks)
    except torch.cuda.OutOfMemoryError:
        if args.no_fallback or args.base_model == FALLBACK_MODEL:
            raise
        print(f"\nOut of GPU memory with {args.base_model}: retrying with {FALLBACK_MODEL}.")
        gc.collect()
        torch.cuda.empty_cache()
        return train(FALLBACK_MODEL, args, callbacks)


def main() -> None:
    run(build_parser().parse_args())


if __name__ == "__main__":
    main()
