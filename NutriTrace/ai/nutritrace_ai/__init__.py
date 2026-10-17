"""Local AI of NutriTrace, module Transport - Traçabilité.

- a Qwen2.5 model fine-tuned with LoRA answers questions about a lot from its real data;
- a scikit-learn model detects anomalies in transports, explained by the same LLM.

Everything runs on this machine: no external API.
"""
