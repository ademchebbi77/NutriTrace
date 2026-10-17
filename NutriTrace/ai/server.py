"""Local AI service of NutriTrace, called by Laravel (App\\Services\\Ai\\AiClient).

    uvicorn server:app --host 127.0.0.1 --port 8001        (from the ai/ folder)

GET  /health              is the service ready, which models are loaded
POST /chat                {context, question} -> {answer, model}
POST /transport/analyze   {transport} -> {is_anomaly, label, confidence, reasons, explanation, model}

Listens on 127.0.0.1 only: nothing is exposed to the network, no external API is called.
"""

import logging
from contextlib import asynccontextmanager
from typing import Any

from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

from nutritrace_ai.anomaly import AnomalyDetector
from nutritrace_ai.config import ANOMALY_MODEL_PATH, NO_LLM, SERVICE_TOKEN
from nutritrace_ai.explain import explain
from nutritrace_ai.features import report
from nutritrace_ai.prompts import chat_messages, explain_messages

log = logging.getLogger("nutritrace_ai")
state: dict[str, Any] = {"llm": None, "anomaly": None, "errors": []}


def load_models() -> None:
    try:
        state["anomaly"] = AnomalyDetector.load(ANOMALY_MODEL_PATH)
    except Exception as error:  # noqa: BLE001 - the service still starts and reports it on /health
        state["errors"].append(f"anomaly model: {error}")
    if NO_LLM:
        return
    try:
        from nutritrace_ai.llm import LocalLLM

        state["llm"] = LocalLLM()
        if not state["llm"].fine_tuned:
            state["errors"].append("no LoRA adapter in models/llm-adapter: using the base model (run scripts/train_llm.py)")
    except Exception as error:  # noqa: BLE001
        state["errors"].append(f"LLM: {error}")
    for error in state["errors"]:
        log.warning(error)


@asynccontextmanager
async def lifespan(_: FastAPI):
    load_models()
    yield


app = FastAPI(title="NutriTrace AI", lifespan=lifespan)


def check_token(x_ai_token: str | None = Header(default=None)) -> None:
    if SERVICE_TOKEN and x_ai_token != SERVICE_TOKEN:
        raise HTTPException(status_code=401, detail="Invalid token")


class ChatRequest(BaseModel):
    context: dict[str, Any]
    question: str = Field(min_length=3, max_length=500)


class AnalyzeRequest(BaseModel):
    transport: dict[str, Any]


@app.get("/health")
async def health() -> dict[str, Any]:
    # async: answered by the event loop itself, even while every worker thread is generating.
    llm = state["llm"]
    return {
        "ready": state["anomaly"] is not None or llm is not None,
        "chat": llm is not None,
        "anomaly": state["anomaly"] is not None,
        "model": llm.name if llm else None,
        "fine_tuned": bool(llm and llm.fine_tuned),
        "errors": state["errors"],
    }


@app.post("/chat", dependencies=[Depends(check_token)])
def chat(request: ChatRequest) -> dict[str, str]:
    llm = state["llm"]
    if llm is None:
        raise HTTPException(status_code=503, detail="The LLM is not loaded")
    try:
        messages = chat_messages(request.context, request.question)
    except (KeyError, TypeError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid lot context: {error}") from error
    return {"answer": llm.generate(messages), "model": llm.name}


@app.post("/transport/analyze", dependencies=[Depends(check_token)])
def analyze(request: AnalyzeRequest) -> dict[str, Any]:
    detector, llm = state["anomaly"], state["llm"]
    if detector is None:
        raise HTTPException(status_code=503, detail="The anomaly model is not loaded")
    try:
        result = detector.predict(request.transport)
    except (KeyError, TypeError, ValueError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid transport: {error}") from error

    if llm is not None:
        explanation = llm.generate(explain_messages(report(request.transport, result["label"], result["confidence"])))
    else:
        explanation = explain(request.transport, result["label"])
    return {**result, "explanation": explanation, "model": "random-forest" + (f" + {llm.name}" if llm else "")}


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="127.0.0.1", port=8001)
