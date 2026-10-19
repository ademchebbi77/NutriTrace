"""French formatting of numbers and dates, identical in the prompts and in the training answers."""

from datetime import datetime

MODE_LABELS = {"truck": "Camion", "van": "Camionnette", "train": "Train", "ship": "Bateau", "plane": "Avion"}


def number(value: float | None, decimals: int = 0) -> str:
    """1234.5 -> '1 234' (decimals=0) or '1 234,5' (decimals=1)."""
    if value is None:
        return "?"
    text = f"{value:,.{decimals}f}".replace(",", " ").replace(".", ",")
    return text


def smart(value: float | None) -> str:
    """As many decimals as the size of the number needs, no trailing zeros: 2 -> '2', 0.057 -> '0,057', 116.9 -> '117'."""
    if value is None:
        return "?"
    decimals = 0 if abs(value) >= 100 else 1 if abs(value) >= 10 else 2 if abs(value) >= 1 else 3
    text = number(value, decimals)
    return text.rstrip("0").rstrip(",") if "," in text else text


def km(value: float | None) -> str:
    if value is None:
        return "distance inconnue"
    return f"{number(value, 0 if value >= 10 or float(value).is_integer() else 1)} km"


def kg(value: float | None) -> str:
    if value is None:
        return "?"
    return f"{number(value, 0 if value >= 100 else 2)} kg"


def quantity(value: float | None, unit: str) -> str:
    if value is None:
        return "?"
    decimals = 0 if float(value).is_integer() else 2
    return f"{number(value, decimals)} {unit}"


def parse_date(value: str | None) -> datetime | None:
    if not value:
        return None
    return datetime.fromisoformat(value.replace("Z", "+00:00"))


def date(value: str | None) -> str:
    moment = parse_date(value)
    return moment.strftime("%d/%m/%Y") if moment else "inconnue"


def datetime_fr(value: str | None) -> str:
    moment = parse_date(value)
    return moment.strftime("%d/%m/%Y %H:%M") if moment else "inconnue"


def duration(hours: float) -> str:
    if hours < 1:
        return f"{round(hours * 60)} min"
    if hours < 48:
        return f"{number(hours, 0 if hours >= 10 else 1)} h"
    return f"{number(hours / 24, 0)} jours"


def mode(value: str) -> str:
    return MODE_LABELS.get(value, value)
