"""Text of a certificate proof. Only PDFs with a text layer are read (no OCR):
a scan or a photo gives an empty text, which the risk model counts as "unreadable"."""

import base64
import binascii
import io


def extract(document: dict | None) -> str | None:
    """document: {"present": bool, "mime": str, "base64": str} as sent by Laravel.
    Returns None without a document, "" when it holds no readable text."""
    if not document or not document.get("present"):
        return None
    if document.get("text") is not None:
        # Already extracted (training data).
        return document["text"]
    if document.get("mime") != "application/pdf" or not document.get("base64"):
        return ""
    try:
        from pypdf import PdfReader

        reader = PdfReader(io.BytesIO(base64.b64decode(document["base64"])))
        return "\n".join(page.extract_text() or "" for page in reader.pages[:5]).strip()
    except (binascii.Error, ValueError, Exception):  # noqa: BLE001 - a broken file is just unreadable
        return ""
