"""The local LLM: Qwen2.5-Instruct with the NutriTrace LoRA adapter merged in.

torch and transformers are imported lazily so the rest of the package (anomaly
model, dataset generation, tests) works without them.
"""

import json
import threading
from pathlib import Path

from .config import ADAPTER_DIR, BASE_MODEL, MAX_NEW_TOKENS


def has_adapter(adapter_dir: Path | None) -> bool:
    return adapter_dir is not None and (Path(adapter_dir) / "adapter_config.json").exists()


class LocalLLM:
    def __init__(self, adapter_dir: Path | None = ADAPTER_DIR, base_model: str | None = None):
        import torch
        from transformers import AutoModelForCausalLM, AutoTokenizer

        self.torch = torch
        adapter = Path(adapter_dir) if has_adapter(adapter_dir) else None
        if base_model is None:
            base_model = json.loads((adapter / "adapter_config.json").read_text(encoding="utf-8"))["base_model_name_or_path"] if adapter else BASE_MODEL

        self.device = "cuda" if torch.cuda.is_available() else "cpu"
        self.tokenizer = AutoTokenizer.from_pretrained(adapter or base_model)
        model = AutoModelForCausalLM.from_pretrained(base_model, dtype=torch.bfloat16 if self.device == "cuda" else torch.float32)
        if adapter:
            from peft import PeftModel

            # Merged once at load time: generation is then as fast as with the base model.
            model = PeftModel.from_pretrained(model, adapter).merge_and_unload()
        self.model = model.to(self.device).eval()

        self.fine_tuned = adapter is not None
        self.name = base_model.split("/")[-1].lower() + ("+nutritrace-lora" if adapter else "")
        # One generation at a time on the GPU.
        self._lock = threading.Lock()

    def generate(self, messages: list[dict[str, str]], max_new_tokens: int = MAX_NEW_TOKENS) -> str:
        prompt = self.tokenizer.apply_chat_template(messages, tokenize=False, add_generation_prompt=True)
        inputs = self.tokenizer(prompt, return_tensors="pt", add_special_tokens=False).to(self.device)
        with self._lock, self.torch.inference_mode():
            output = self.model.generate(
                **inputs,
                max_new_tokens=max_new_tokens,
                do_sample=False,
                repetition_penalty=1.05,
                pad_token_id=self.tokenizer.eos_token_id,
            )
        return self.tokenizer.decode(output[0][inputs["input_ids"].shape[1]:], skip_special_tokens=True).strip()
