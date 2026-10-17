"""Full training of one module, as started from the Laravel admin page (POST /train).

    python scripts/train_module.py --module distribution [--epochs 1] [--skip-llm]

Stages, reported live in models/train-status.json:
1. dataset      generate the fine-tuning dataset from the exported data (php artisan ai:export,
                run by Laravel just before) and synthetic data;
2. classifiers  train the scikit-learn models of the module (a few seconds, CPU);
3. llm          fine-tune the module's LoRA adapter (GPU, the long part);
4. evaluation   score the new adapter on part of the validation set.

The service unloads its LLM before starting this script (GPU memory) and reloads the
models when it ends.
"""

import argparse
import json
import subprocess
import sys
import traceback
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))
sys.path.insert(0, str(ROOT / "scripts"))

from nutritrace_ai import train_status  # noqa: E402
from nutritrace_ai.config import MODELS_DIR, MODULES  # noqa: E402

CLASSIFIERS = {"transport": "train_anomaly.py", "distribution": "train_distribution_models.py"}


def step(script: str, *arguments: str) -> None:
    """Run a script of this folder in a child process and stop on failure."""
    completed = subprocess.run([sys.executable, "-X", "utf8", str(ROOT / "scripts" / script), *arguments], cwd=ROOT)
    if completed.returncode != 0:
        raise RuntimeError(f"{script} failed (exit code {completed.returncode})")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--module", choices=list(MODULES), required=True)
    parser.add_argument("--epochs", type=float, default=1)
    parser.add_argument("--skip-llm", action="store_true", help="only the dataset and the scikit-learn models")
    parser.add_argument("--resume", action="store_true", help="continue the LLM fine-tuning from its last checkpoint (same dataset)")
    parser.add_argument("--eval-limit", type=int, default=60)
    args = parser.parse_args()

    train_status.start(args.module)
    try:
        # A resumed run must see exactly the dataset of its checkpoint: no regeneration.
        if not args.resume:
            train_status.write(stage="dataset", message="Génération du jeu de données")
            step("generate_dataset.py", "--module", args.module)

            train_status.write(stage="classifiers", message="Entraînement des modèles scikit-learn")
            step(CLASSIFIERS[args.module])

        if not args.skip_llm:
            import train_llm

            train_status.write(stage="llm", message="Reprise depuis le dernier point de sauvegarde" if args.resume else "Chargement du modèle de base")
            llm_args = train_llm.build_parser().parse_args(["--module", args.module, "--epochs", str(args.epochs)] + (["--resume"] if args.resume else []))
            info = train_llm.run(llm_args, callbacks=[train_status.trainer_callback()])
            train_status.write(eval_loss=info["eval_loss"], loss=info["train_loss"], minutes=info["minutes"])

            train_status.write(stage="evaluation", message="Évaluation sur des données jamais vues", step=0, total=0, eta_seconds=None)
            step("evaluate_llm.py", "--module", args.module, "--limit", str(args.eval_limit), "--show", "0")
            result = json.loads((MODELS_DIR / f"eval-{args.module}-finetuned.json").read_text(encoding="utf-8"))
            train_status.write(accuracy=round(result["accuracy"], 4))

        train_status.write(state="done", stage="done", message="Entraînement terminé", eta_seconds=0)
    except BaseException as error:  # noqa: BLE001 - includes KeyboardInterrupt, reported to the admin page
        train_status.write(state="failed", message="Échec de l'entraînement", error=f"{type(error).__name__}: {error}")
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    main()
