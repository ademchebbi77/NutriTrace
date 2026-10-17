"""Paths and settings, overridable with environment variables."""

import os
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

DATA_DIR = ROOT / "data"
# Written by `php artisan ai:export`.
EXPORT_DIR = DATA_DIR / "export"
# Written by scripts/generate_dataset.py.
DATASET_DIR = DATA_DIR / "dataset"

MODELS_DIR = ROOT / "models"
ANOMALY_MODEL_PATH = MODELS_DIR / "anomaly.joblib"
ADAPTER_DIR = Path(os.environ.get("NUTRITRACE_ADAPTER_DIR", MODELS_DIR / "llm-adapter"))

# Base model to fine-tune; the 0.5B one is used when the 1.5B one does not fit in GPU memory.
BASE_MODEL = os.environ.get("NUTRITRACE_BASE_MODEL", "Qwen/Qwen2.5-1.5B-Instruct")
FALLBACK_MODEL = "Qwen/Qwen2.5-0.5B-Instruct"

# Shared secret with Laravel (AI_SERVICE_TOKEN in its .env); no check when empty.
SERVICE_TOKEN = os.environ.get("AI_SERVICE_TOKEN") or None

# Set to 1 to start the service without the LLM (anomaly check with template explanations only).
NO_LLM = os.environ.get("NUTRITRACE_NO_LLM") == "1"

MAX_NEW_TOKENS = int(os.environ.get("NUTRITRACE_MAX_NEW_TOKENS", 200))

# Same values as config/trust.php and config/footprint.php of the Laravel app.
LOCAL_RADIUS_KM = 150
EMISSION_FACTORS = {"truck": 0.105, "van": 0.250, "train": 0.028, "ship": 0.016, "plane": 0.602}
