"""Live state of a training run, in models/train-status.json.

Written by scripts/train_module.py (and its Trainer callback), read by the service for
GET /train/status, which the Laravel admin page polls.
"""

import json
import time
from datetime import datetime
from typing import Any

from .config import TRAIN_STATUS_PATH

STAGES = ["export", "dataset", "classifiers", "llm", "evaluation", "done"]


def read() -> dict[str, Any]:
    try:
        return json.loads(TRAIN_STATUS_PATH.read_text(encoding="utf-8"))
    except (FileNotFoundError, json.JSONDecodeError):
        return {"state": "idle"}


def write(**fields: Any) -> dict[str, Any]:
    status = {**read(), **fields, "updated_at": datetime.now().isoformat(timespec="seconds")}
    TRAIN_STATUS_PATH.parent.mkdir(parents=True, exist_ok=True)
    tmp = TRAIN_STATUS_PATH.with_suffix(".tmp")
    tmp.write_text(json.dumps(status, indent=2, ensure_ascii=False), encoding="utf-8")
    tmp.replace(TRAIN_STATUS_PATH)
    return status


def start(module: str) -> dict[str, Any]:
    TRAIN_STATUS_PATH.unlink(missing_ok=True)
    return write(state="running", module=module, stage="dataset", step=0, total=0, loss=None, eval_loss=None,
                 eta_seconds=None, message="Préparation", started_at=datetime.now().isoformat(timespec="seconds"), error=None)


def trainer_callback():
    """transformers callback reporting the step, the losses and the remaining time."""
    from transformers import TrainerCallback

    class StatusCallback(TrainerCallback):
        def on_train_begin(self, args, state, control, **kwargs):
            self.started = time.time()
            write(stage="llm", step=state.global_step, total=state.max_steps, message="Affinage du LLM (LoRA)")

        def on_step_end(self, args, state, control, **kwargs):
            done = max(1, state.global_step)
            elapsed = time.time() - self.started
            write(step=state.global_step, total=state.max_steps, eta_seconds=int(elapsed / done * (state.max_steps - done)))

        def on_log(self, args, state, control, logs=None, **kwargs):
            if logs and "loss" in logs:
                write(loss=round(float(logs["loss"]), 4))
            if logs and "eval_loss" in logs:
                write(eval_loss=round(float(logs["eval_loss"]), 4))

    return StatusCallback()
