"""Chat with the model in the terminal about an exported lot.

    python scripts/chat_cli.py                   # first lot of data/export/lots.json
    python scripts/chat_cli.py LOT-2026-034
    python scripts/chat_cli.py --base            # base model, without fine-tuning
"""

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from nutritrace_ai.config import ADAPTER_DIR, EXPORT_DIR  # noqa: E402
from nutritrace_ai.context import render  # noqa: E402
from nutritrace_ai.llm import LocalLLM  # noqa: E402
from nutritrace_ai.prompts import chat_messages  # noqa: E402


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("lot", nargs="?", help="lot number, e.g. LOT-2026-034")
    parser.add_argument("--base", action="store_true")
    parser.add_argument("--show-context", action="store_true")
    args = parser.parse_args()

    lots = json.loads((EXPORT_DIR / "lots.json").read_text(encoding="utf-8"))
    ctx = next((lot for lot in lots if lot["lot"]["number"] == args.lot), None) if args.lot else lots[0]
    if ctx is None:
        sys.exit(f"Lot {args.lot} not found in {EXPORT_DIR / 'lots.json'}")

    llm = LocalLLM(adapters={} if args.base else {"transport": ADAPTER_DIR})
    print(f"{llm.model_name('transport')} — lot {ctx['lot']['number']} ({ctx['product']['name']}). Empty line to quit.")
    if args.show_context:
        print(render(ctx))
    while question := input("\nVous : ").strip():
        print(f"Assistant : {llm.generate(chat_messages(ctx, question), module='transport')}")


if __name__ == "__main__":
    main()
