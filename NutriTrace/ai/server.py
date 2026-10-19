"""Local AI service of NutriTrace, called by Laravel (App\\Services\\Ai\\AiClient).

    uvicorn server:app --host 127.0.0.1 --port 8001        (from the ai/ folder, or ai/start.bat)

One Qwen2.5 base model in GPU memory with a LoRA adapter per module, plus three
scikit-learn models. Listens on 127.0.0.1 only; no external API is called.

GET  /health                      what is loaded, is a training running
Transport - Traçabilité
POST /chat                        {context, question} -> {answer, model}
POST /transport/analyze           {transport} -> {is_anomaly, label, confidence, reasons, explanation, model}
Distribution - Certification
POST /distribution/chat           {context, question} -> {answer, model}
POST /distribution/reception      {reception} -> {decision, confidence, reasons, explanation, transport_label, model}
POST /certification/risk          {certificate, explain} -> {level, risk, confidence, reasons, explanation, model}
POST /certification/risk/batch    {certificates} -> {results: [...]}
Training
GET  /models                      trained adapters and models, with their scores
POST /train                       {module, epochs} start a training run (separate process)
GET  /train/status                progress of the run
POST /train/cancel                stop it
"""

import json
import logging
import os
import signal
import subprocess
import sys
import threading
from contextlib import asynccontextmanager
from typing import Any

from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

from nutritrace_ai import advisor, certificates, impact_check, moderation, reception, train_status, transformations
from nutritrace_ai.anomaly import AnomalyDetector
from nutritrace_ai.config import (ANOMALY_MODEL_PATH, CERT_RISK_MODEL_PATH, MODELS_DIR, MODULES, NO_LLM, RECEPTION_MODEL_PATH, ROOT,
                                  SERVICE_TOKEN, SHELF_LIFE_MODEL_PATH, TRAIN_LOG_PATH, TRANSFORMATION_MODEL_PATH,
                                  IMPACT_CHECK_MODEL_PATH, MODERATION_MODEL_PATH)
from nutritrace_ai.distribution_models import CertificateRiskModel, ReceptionModel
from nutritrace_ai.explain import explain
from nutritrace_ai.features import report
from nutritrace_ai.pdf_text import extract
from nutritrace_ai.prompts import (certificate_messages, chat_messages, explain_messages, impact_messages, reception_messages,
                                   stock_messages, transformation_messages)
from nutritrace_ai.transformation_models import ShelfLifeModel, TransformationModel
from nutritrace_ai.impact_models import ImpactCheckModel, ModerationModel

log = logging.getLogger("nutritrace_ai")
state: dict[str, Any] = {"llm": None, "anomaly": None, "cert": None, "reception": None, "transformation": None, "shelf": None,
                         "impact_check": None, "moderation": None, "errors": [], "process": None}
CLASSIFIERS = {
    "anomaly": (AnomalyDetector, ANOMALY_MODEL_PATH),
    "cert": (CertificateRiskModel, CERT_RISK_MODEL_PATH),
    "reception": (ReceptionModel, RECEPTION_MODEL_PATH),
    "transformation": (TransformationModel, TRANSFORMATION_MODEL_PATH),
    "shelf": (ShelfLifeModel, SHELF_LIFE_MODEL_PATH),
    "impact_check": (ImpactCheckModel, IMPACT_CHECK_MODEL_PATH),
    "moderation": (ModerationModel, MODERATION_MODEL_PATH),
}


def load_models() -> None:
    state["errors"] = []
    for key, (cls, path) in CLASSIFIERS.items():
        try:
            state[key] = cls.load(path)
        except Exception as error:  # noqa: BLE001 - the service still starts and reports it on /health
            state[key] = None
            state["errors"].append(f"{path.name}: {error}")
    if NO_LLM or training():
        return
    try:
        from nutritrace_ai.llm import LocalLLM

        state["llm"] = LocalLLM()
        missing = [name for name in MODULES if name not in state["llm"].adapters]
        if missing:
            state["errors"].append(f"no trained adapter for {', '.join(missing)}: base model used (train it from the admin page)")
        for name, base in state["llm"].skipped.items():
            state["errors"].append(f"adapter {name} was trained on {base}, not on the loaded base model: ignored")
    except Exception as error:  # noqa: BLE001
        state["llm"] = None
        state["errors"].append(f"LLM: {error}")
    for error in state["errors"]:
        log.warning(error)


def unload_llm() -> None:
    llm, state["llm"] = state["llm"], None
    if llm is not None:
        llm.unload()


def training() -> bool:
    process = state.get("process")
    return process is not None and process.poll() is None


@asynccontextmanager
async def lifespan(_: FastAPI):
    if train_status.read().get("state") == "running":
        # The service stopped during a run: that run is gone.
        train_status.write(state="failed", message="Entraînement interrompu (service arrêté)")
    load_models()
    yield


app = FastAPI(title="NutriTrace AI", lifespan=lifespan)


def check_token(x_ai_token: str | None = Header(default=None)) -> None:
    if SERVICE_TOKEN and x_ai_token != SERVICE_TOKEN:
        raise HTTPException(status_code=401, detail="Invalid token")


def need(key: str) -> Any:
    if state[key] is None:
        raise HTTPException(status_code=503, detail="training" if training() else f"{key} model not loaded")
    return state[key]


def llm_for(module: str):
    """The LLM, or None (then template explanations are used)."""
    return state["llm"] if state["llm"] is not None else None


# --- health ------------------------------------------------------------------------------

@app.get("/health")
async def health() -> dict[str, Any]:
    # async: answered by the event loop itself, even while every worker thread is generating.
    llm = state["llm"]
    return {
        "ready": any(state[key] is not None for key in ("llm", *CLASSIFIERS)),
        "chat": llm is not None,
        "anomaly": state["anomaly"] is not None,
        "certification": state["cert"] is not None,
        "reception": state["reception"] is not None,
        "transformation": state["transformation"] is not None,
        "shelf_life": state["shelf"] is not None,
        "impact_check": state["impact_check"] is not None,
        "moderation": state["moderation"] is not None,
        "adapters": llm.adapters if llm else [],
        "model": llm.name if llm else None,
        "fine_tuned": bool(llm and llm.fine_tuned),
        "training": training(),
        "errors": state["errors"],
    }


# --- transport - traçabilité ----------------------------------------------------------------

class ChatRequest(BaseModel):
    context: dict[str, Any]
    question: str = Field(min_length=3, max_length=500)


class AnalyzeRequest(BaseModel):
    transport: dict[str, Any]


@app.post("/chat", dependencies=[Depends(check_token)])
def chat(request: ChatRequest) -> dict[str, str]:
    llm = need("llm")
    try:
        messages = chat_messages(request.context, request.question)
    except (KeyError, TypeError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid lot context: {error}") from error
    return {"answer": llm.generate(messages, module="transport"), "model": llm.model_name("transport")}


@app.post("/transport/analyze", dependencies=[Depends(check_token)])
def analyze(request: AnalyzeRequest) -> dict[str, Any]:
    detector, llm = need("anomaly"), llm_for("transport")
    try:
        result = detector.predict(request.transport)
    except (KeyError, TypeError, ValueError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid transport: {error}") from error

    if llm is not None:
        explanation = llm.generate(explain_messages(report(request.transport, result["label"], result["confidence"])), module="transport")
    else:
        explanation = explain(request.transport, result["label"])
    return {**result, "explanation": explanation, "model": "random-forest" + (f" + {llm.model_name('transport')}" if llm else "")}


# --- distribution - certification -----------------------------------------------------------

class ReceptionRequest(BaseModel):
    reception: dict[str, Any]


class CertificateRequest(BaseModel):
    certificate: dict[str, Any]
    explain: bool = False


class CertificateBatchRequest(BaseModel):
    certificates: list[dict[str, Any]] = Field(max_length=200)


@app.post("/distribution/chat", dependencies=[Depends(check_token)])
def distribution_chat(request: ChatRequest) -> dict[str, str]:
    llm = need("llm")
    try:
        messages = stock_messages(request.context, request.question)
    except (KeyError, TypeError, ValueError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid distributor context: {error}") from error
    return {"answer": llm.generate(messages, module="distribution"), "model": llm.model_name("distribution")}


@app.post("/distribution/reception", dependencies=[Depends(check_token)])
def check_reception(request: ReceptionRequest) -> dict[str, Any]:
    model, llm = need("reception"), llm_for("distribution")
    payload = request.reception
    try:
        # The transport model of the other module tells whether the trip itself is plausible.
        transport_label = state["anomaly"].predict(payload["transport"])["label"] if payload.get("transport") and state["anomaly"] else None
        result = model.predict(payload, transport_label)
    except (KeyError, TypeError, ValueError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid reception: {error}") from error

    if llm is not None:
        text = reception.report(payload, result["decision"], result["confidence"], transport_label)
        explanation = llm.generate(reception_messages(text), module="distribution")
    else:
        explanation = reception.explain(payload, result["decision"], transport_label=transport_label)
    return {**result, "explanation": explanation, "transport_label": transport_label,
            "model": "random-forest" + (f" + {llm.model_name('distribution')}" if llm else "")}


def prepared(certificate: dict[str, Any]) -> dict[str, Any]:
    """Read the PDF once: the features look at its text several times."""
    document = certificate.get("document") or {}
    if document.get("present") and "text" not in document:
        certificate = {**certificate, "document": {**document, "text": extract(document), "base64": None}}
    return certificate


def risk_of(certificate: dict[str, Any], with_explanation: bool) -> dict[str, Any]:
    model, llm = need("cert"), llm_for("distribution")
    certificate = prepared(certificate)
    try:
        result = model.predict(certificate)
    except (KeyError, TypeError, ValueError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid certificate: {error}") from error
    result["id"] = certificate.get("id")
    result["document_readable"] = bool((certificate.get("document") or {}).get("text"))
    result["explanation"] = None
    if with_explanation:
        if llm is not None:
            text = certificates.report(certificate, result["level"], result["risk"])
            result["explanation"] = llm.generate(certificate_messages(text), module="distribution")
        else:
            result["explanation"] = certificates.explain(certificate, result["level"])
    result["model"] = "random-forest" + (f" + {llm.model_name('distribution')}" if with_explanation and llm else "")
    return result


@app.post("/certification/risk", dependencies=[Depends(check_token)])
def certificate_risk(request: CertificateRequest) -> dict[str, Any]:
    return risk_of(request.certificate, request.explain)


@app.post("/certification/risk/batch", dependencies=[Depends(check_token)])
def certificate_risk_batch(request: CertificateBatchRequest) -> dict[str, Any]:
    return {"results": [risk_of(certificate, False) for certificate in request.certificates]}


# --- transformation - lots ------------------------------------------------------------------

class TransformationRequest(BaseModel):
    transformation: dict[str, Any]
    explain: bool = False
    audience: str = Field(default="transformer", pattern="^(transformer|admin)$")


class TransformationBatchRequest(BaseModel):
    transformations: list[dict[str, Any]] = Field(max_length=300)


class LotsRequest(BaseModel):
    lots: list[dict[str, Any]] = Field(max_length=1000)


def check_transformation(payload: dict[str, Any], with_explanation: bool, audience: str) -> dict[str, Any]:
    model, llm = need("transformation"), state["llm"]
    try:
        result = model.predict(payload)
    except (KeyError, TypeError, ValueError, ZeroDivisionError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid transformation: {error}") from error
    result["id"] = payload.get("id")
    result["explanation"] = None
    if with_explanation:
        if llm is not None:
            text = transformations.report(payload, result["label"], result["confidence"], audience)
            result["explanation"] = llm.generate(transformation_messages(text, audience), module="transformation")
        else:
            result["explanation"] = transformations.explain(payload, result["label"], audience)
    result["model"] = "random-forest" + (f" + {llm.model_name('transformation')}" if with_explanation and llm else "")
    return result


@app.post("/transformation/check", dependencies=[Depends(check_token)])
def transformation_check(request: TransformationRequest) -> dict[str, Any]:
    return check_transformation(request.transformation, request.explain, request.audience)


@app.post("/transformation/check/batch", dependencies=[Depends(check_token)])
def transformation_check_batch(request: TransformationBatchRequest) -> dict[str, Any]:
    return {"results": [check_transformation(payload, False, "admin") for payload in request.transformations]}


@app.post("/lots/expiry/batch", dependencies=[Depends(check_token)])
def lots_expiry(request: LotsRequest) -> dict[str, Any]:
    model = need("shelf")
    try:
        return {"results": [model.predict(lot) for lot in request.lots], "model": "gradient-boosting"}
    except (KeyError, TypeError, ValueError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid lot: {error}") from error


# --- impact environnemental - avis et signalements ------------------------------------------

class DeclarationRequest(BaseModel):
    declaration: dict[str, Any]
    explain: bool = False


class AdviceRequest(BaseModel):
    lot: dict[str, Any]


class ReviewRequest(BaseModel):
    review: dict[str, Any]
    explain: bool = False


def explained(module_text: str, task: str, fallback: str) -> tuple[str, str]:
    """LLM text (impact adapter) or the reference text when the LLM is not loaded."""
    llm = state["llm"]
    if llm is None:
        return fallback, ""
    return llm.generate(impact_messages(module_text, task), module="impact"), f" + {llm.model_name('impact')}"


@app.post("/impact/check", dependencies=[Depends(check_token)])
def check_impact(request: DeclarationRequest) -> dict[str, Any]:
    model = need("impact_check")
    try:
        result = model.predict(request.declaration)
    except (KeyError, TypeError, ValueError, ZeroDivisionError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid declaration: {error}") from error
    results = result.pop("_results", [])
    result["explanation"], suffix = None, ""
    if request.explain and results:
        text = impact_check.report(request.declaration, results, result["label"], result["confidence"])
        result["explanation"], suffix = explained(text, "check", impact_check.explain(results, result["label"]))
    result["model"] = "random-forest" + suffix
    return result


@app.post("/impact/advice", dependencies=[Depends(check_token)])
def impact_advice(request: AdviceRequest) -> dict[str, Any]:
    try:
        analysis = advisor.analyse(request.lot)
        levers = advisor.advice(request.lot)
    except (KeyError, TypeError, ValueError, ZeroDivisionError) as error:
        raise HTTPException(status_code=422, detail=f"Invalid lot: {error}") from error
    explanation, suffix = explained(advisor.report(request.lot), "advice", advisor.explain(request.lot))
    return {"score": analysis["score"], "grade": analysis["grade"], "target": analysis["target"], "levers": levers,
            "shares": analysis["shares"], "explanation": explanation, "model": "règles de score" + suffix}


@app.post("/reviews/moderate", dependencies=[Depends(check_token)])
def moderate(request: ReviewRequest) -> dict[str, Any]:
    result = need("moderation").predict(request.review)
    result["explanation"], suffix = None, ""
    if request.explain:
        result["explanation"], suffix = explained(moderation.report(request.review, result), "moderation", moderation.explain(request.review, result))
    result["model"] = "tf-idf + régression logistique" + suffix
    return result


# --- training -------------------------------------------------------------------------------

class TrainRequest(BaseModel):
    module: str
    epochs: float = Field(default=1, gt=0, le=5)
    # Continue an interrupted run from its last checkpoint.
    resume: bool = False


def checkpoint_of(module: str) -> str | None:
    folder = MODELS_DIR / "checkpoints" / module
    checkpoints = sorted(folder.glob("checkpoint-*"), key=lambda p: int(p.name.split("-")[-1])) if folder.exists() else []
    return checkpoints[-1].name if checkpoints else None


def adapter_info(module: str) -> dict[str, Any]:
    folder = MODULES[module]["adapter"]
    info: dict[str, Any] = {"module": module, "label": MODULES[module]["label"], "trained": (folder / "adapter_config.json").exists(),
                            "checkpoint": checkpoint_of(module)}
    for name, key in (("training_info.json", "training"),):
        path = folder / name
        if path.exists():
            data = json.loads(path.read_text(encoding="utf-8"))
            info[key] = {k: data.get(k) for k in ("base_model", "trained_at", "train_examples", "val_examples", "epochs", "train_loss", "eval_loss", "minutes")}
    evaluation = MODELS_DIR / f"eval-{module}-finetuned.json"
    if module == "transport" and not evaluation.exists():
        evaluation = MODELS_DIR / "eval-finetuned.json"
    if evaluation.exists():
        data = json.loads(evaluation.read_text(encoding="utf-8"))
        info["evaluation"] = {"accuracy": data.get("accuracy"), "questions": len(data.get("results", [])),
                              "per_task": data.get("per_task")}
    return info


@app.get("/models", dependencies=[Depends(check_token)])
def models() -> dict[str, Any]:
    classifiers = {}
    for key, (_, path) in CLASSIFIERS.items():
        metrics = path.with_name(path.stem + "-metrics.json")
        classifiers[key] = {"loaded": state[key] is not None,
                            "metrics": json.loads(metrics.read_text(encoding="utf-8")) if metrics.exists() else None}
    for key in ("anomaly",):
        if classifiers[key]["metrics"] is None and (MODELS_DIR / "anomaly-metrics.json").exists():
            classifiers[key]["metrics"] = json.loads((MODELS_DIR / "anomaly-metrics.json").read_text(encoding="utf-8"))
    return {"adapters": [adapter_info(name) for name in MODULES], "classifiers": classifiers}


def watch(process: subprocess.Popen) -> None:
    """When the run ends (finished, failed or cancelled), reload every model."""
    process.wait()
    if train_status.read().get("state") == "running":
        train_status.write(state="failed", message="Le processus d'entraînement s'est arrêté", error=f"exit code {process.returncode}")
    load_models()


@app.post("/train", dependencies=[Depends(check_token)])
def train(request: TrainRequest) -> dict[str, Any]:
    # Only a name taken from MODULES reaches the command line, never the string sent by the client.
    module = next((name for name in MODULES if name == request.module), None)
    if module is None:
        raise HTTPException(status_code=422, detail="Unknown module")
    if training():
        raise HTTPException(status_code=409, detail="A training run is already in progress")
    if request.resume and not checkpoint_of(module):
        raise HTTPException(status_code=409, detail="No checkpoint to resume from: start a new training run")

    # The GPU holds one 1.5B model: the LLM is unloaded while training, cached answers keep working.
    unload_llm()
    status = train_status.start(module)
    log_file = TRAIN_LOG_PATH.open("a" if request.resume else "w", encoding="utf-8")
    flags = subprocess.CREATE_NEW_PROCESS_GROUP if os.name == "nt" else 0
    epochs = f"{float(request.epochs):g}"
    command = [sys.executable, "-X", "utf8", str(ROOT / "scripts" / "train_module.py"), "--module", module, "--epochs", epochs]
    if NO_LLM:
        command.append("--skip-llm")
    state["process"] = subprocess.Popen(
        command + (["--resume"] if request.resume else []),
        cwd=ROOT, stdout=log_file, stderr=subprocess.STDOUT, creationflags=flags, start_new_session=os.name != "nt",
    )
    threading.Thread(target=watch, args=(state["process"],), daemon=True).start()
    return {**status, "running": True}


def log_tail(lines: int = 15) -> list[str]:
    try:
        text = TRAIN_LOG_PATH.read_text(encoding="utf-8", errors="replace")
    except FileNotFoundError:
        return []
    # Progress bars rewrite their line with \r: keep the last version of each line.
    return [line.split("\r")[-1] for line in text.splitlines() if line.strip()][-lines:]


@app.get("/train/status", dependencies=[Depends(check_token)])
def status() -> dict[str, Any]:
    return {**train_status.read(), "running": training(), "log": log_tail()}


@app.post("/train/cancel", dependencies=[Depends(check_token)])
def cancel() -> dict[str, Any]:
    process = state.get("process")
    if not training():
        raise HTTPException(status_code=409, detail="No training run in progress")
    if os.name == "nt":
        # The whole tree: the run starts child processes (dataset, evaluation).
        subprocess.run(["taskkill", "/PID", str(process.pid), "/T", "/F"], capture_output=True)
    else:
        os.killpg(process.pid, signal.SIGTERM)
    train_status.write(state="cancelled", message="Entraînement annulé")
    return {**train_status.read(), "running": False}


# --- production forecasting -----------------------------------------------------------------

class ProductionRecord(BaseModel):
    production_date: str
    # No gt=0 here: invalid quantities are filtered inside production.forecast(),
    # a strict check would reject the whole request for a single bad record.
    quantity: float
    unit: str
    production_method: str = "conventional"


class ProductionForecastRequest(BaseModel):
    records: list[ProductionRecord] = Field(min_length=1, max_length=500)
    unit: str


@app.post("/production/forecast", dependencies=[Depends(check_token)])
def production_forecast(request: ProductionForecastRequest) -> dict[str, Any]:
    """
    Estimate future production quantity from historical records.
    All records must share the same unit (enforced by the caller).
    Returns a structured result with status, history, statistics, trend,
    baseline estimate, and an ML prediction when enough data is available.
    """
    from nutritrace_ai.production import forecast

    records = [r.model_dump() for r in request.records]
    try:
        return forecast(records, request.unit)
    except Exception as error:  # noqa: BLE001
        log.warning("production/forecast error: %s", error)
        raise HTTPException(status_code=422, detail=str(error)) from error


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="127.0.0.1", port=8001)
