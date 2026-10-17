"""The local LLM: one Qwen2.5-Instruct base model in GPU memory, with one LoRA adapter
per module (Transport, Distribution-Certification) switched per request.

torch and transformers are imported lazily so the rest of the package (scikit-learn
models, dataset generation, tests) works without them.
"""

import json
import threading
from pathlib import Path

from .config import BASE_MODEL, MAX_NEW_TOKENS, MODULES


def has_adapter(adapter_dir: Path | None) -> bool:
    return adapter_dir is not None and (Path(adapter_dir) / "adapter_config.json").exists()


def adapter_base(adapter_dir: Path) -> str:
    return json.loads((Path(adapter_dir) / "adapter_config.json").read_text(encoding="utf-8"))["base_model_name_or_path"]


class LocalLLM:
    def __init__(self, adapters: dict[str, Path | None] | None = None, base_model: str | None = None):
        """adapters: {module: folder}. By default every module adapter trained so far; {} for the base model alone."""
        import torch
        from transformers import AutoModelForCausalLM, AutoTokenizer

        self.torch = torch
        if adapters is None:
            adapters = {name: module["adapter"] for name, module in MODULES.items()}
        found = {name: Path(path) for name, path in adapters.items() if has_adapter(path)}

        if base_model is None:
            base_model = adapter_base(next(iter(found.values()))) if found else BASE_MODEL
        # An adapter only fits the base model it was trained on.
        self.skipped = {name: adapter_base(path) for name, path in found.items() if adapter_base(path) != base_model}
        found = {name: path for name, path in found.items() if name not in self.skipped}

        self.device = "cuda" if torch.cuda.is_available() else "cpu"
        self.tokenizer = AutoTokenizer.from_pretrained(base_model)
        model = AutoModelForCausalLM.from_pretrained(base_model, dtype=torch.bfloat16 if self.device == "cuda" else torch.float32)
        if found:
            from peft import PeftModel

            names = list(found)
            model = PeftModel.from_pretrained(model, found[names[0]], adapter_name=names[0])
            for name in names[1:]:
                model.load_adapter(found[name], adapter_name=name)
        self.model = model.to(self.device).eval()

        self.adapters = list(found)
        self.base_name = base_model.split("/")[-1].lower()
        self.name = self.base_name + (f"+lora[{','.join(self.adapters)}]" if self.adapters else "")
        # One generation at a time on the GPU (the active adapter is shared state).
        self._lock = threading.Lock()

    @property
    def fine_tuned(self) -> bool:
        return bool(self.adapters)

    def model_name(self, module: str) -> str:
        return f"{self.base_name}+{module}-lora" if module in self.adapters else self.base_name

    def generate(self, messages: list[dict[str, str]], module: str = "transport", max_new_tokens: int = MAX_NEW_TOKENS) -> str:
        prompt = self.tokenizer.apply_chat_template(messages, tokenize=False, add_generation_prompt=True)
        inputs = self.tokenizer(prompt, return_tensors="pt", add_special_tokens=False).to(self.device)
        with self._lock, self.torch.inference_mode():
            if module in self.adapters:
                self.model.set_adapter(module)
                output = self._generate(inputs, max_new_tokens)
            elif self.adapters:
                # No adapter for this module yet: answer with the base model alone.
                with self.model.disable_adapter():
                    output = self._generate(inputs, max_new_tokens)
            else:
                output = self._generate(inputs, max_new_tokens)
        return self.tokenizer.decode(output[0][inputs["input_ids"].shape[1]:], skip_special_tokens=True).strip()

    def _generate(self, inputs, max_new_tokens: int):
        return self.model.generate(
            **inputs,
            max_new_tokens=max_new_tokens,
            do_sample=False,
            repetition_penalty=1.05,
            pad_token_id=self.tokenizer.eos_token_id,
        )

    def unload(self) -> None:
        """Free the GPU (before a training run)."""
        del self.model
        import gc

        gc.collect()
        if self.device == "cuda":
            self.torch.cuda.empty_cache()
