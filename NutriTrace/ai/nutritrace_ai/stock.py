"""A distributor's stock and sales (App\\Services\\Ai\\DistributionAiContext::forDistributor),
summarised and turned into the text given to the LLM.

Totals, rankings and dates are computed here rather than left to the model, and the
same function renders the training prompts and the live prompts.
"""

from collections import defaultdict
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from typing import Any

from . import formatting as fmt

MONTHS = ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"]
MAX_LOTS = 30
SOON_DAYS = 7


@dataclass
class Line:
    lot: str
    product: str
    unit: str
    status: str
    status_label: str
    quantity: float
    remaining: float
    expiration: datetime | None
    days_left: int | None
    received: datetime | None
    sender: str
    sold_total: float = 0.0
    sold_30: float = 0.0
    sold_month: float = 0.0
    sales: int = 0
    last_sale: datetime | None = None

    @property
    def in_stock(self) -> bool:
        return self.status in ("received", "in_store") and self.remaining > 0


@dataclass
class Stock:
    today: datetime
    distributor: dict[str, Any]
    pending: list[Line] = field(default_factory=list)
    stock: list[Line] = field(default_factory=list)
    sold_out: list[Line] = field(default_factory=list)
    refused: list[Line] = field(default_factory=list)
    month_by_product: dict[str, tuple[float, str]] = field(default_factory=dict)
    last_sales: list[dict[str, Any]] = field(default_factory=list)

    @property
    def month(self) -> str:
        return f"{MONTHS[self.today.month - 1]} {self.today.year}"

    @property
    def expired(self) -> list[Line]:
        return [l for l in self.stock if l.days_left is not None and l.days_left < 0]

    @property
    def expiring(self) -> list[Line]:
        return sorted([l for l in self.stock if l.days_left is not None and 0 <= l.days_left <= SOON_DAYS], key=lambda l: l.days_left)

    @property
    def sellable(self) -> list[Line]:
        """In stock, not expired, and received at least a week ago (enough to judge its sales)."""
        return [l for l in self.stock if not (l.days_left is not None and l.days_left < 0)
                and l.received and (self.today - l.received).days >= 7]

    @property
    def slowest(self) -> Line | None:
        lines = self.sellable
        return min(lines, key=lambda l: (l.sold_30 / l.quantity if l.quantity else 0, l.lot)) if lines else None

    @property
    def best(self) -> Line | None:
        lines = [l for l in self.stock + self.sold_out if l.sold_30 > 0]
        return max(lines, key=lambda l: (l.sold_30 / l.quantity if l.quantity else 0, l.lot)) if lines else None

    @property
    def promotion(self) -> Line | None:
        """Soonest expiry among lots that still have at least 30 % of their quantity."""
        lines = [l for l in self.stock if l.days_left is not None and 0 <= l.days_left <= 14 and l.quantity and l.remaining / l.quantity >= 0.3]
        return min(lines, key=lambda l: l.days_left) if lines else None


def _date(value: str | None) -> datetime | None:
    moment = fmt.parse_date(value)
    return moment.replace(tzinfo=None) if moment else None


def summarize(ctx: dict[str, Any]) -> Stock:
    today = _date(ctx.get("today")) or datetime.now()
    s = Stock(today=today, distributor=ctx.get("distributor") or {})
    lines: dict[str, Line] = {}
    for d in ctx.get("distributions") or []:
        expiration = _date(d.get("expiration_date"))
        line = Line(
            lot=d["lot"], product=d["product"], unit=d.get("unit", ""), status=d.get("status", ""),
            status_label=d.get("lot_status_label") or d.get("status_label", ""), quantity=float(d.get("quantity") or 0),
            remaining=float(d.get("remaining") or 0), expiration=expiration,
            days_left=(expiration.date() - today.date()).days if expiration else None,
            received=_date(d.get("received_at")), sender=d.get("sender") or "?",
        )
        lines[line.lot] = line

    month_start = today.replace(day=1, hour=0, minute=0, second=0, microsecond=0)
    by_product: dict[str, list] = defaultdict(lambda: [0.0, ""])
    for sale in sorted(ctx.get("sales") or [], key=lambda x: x["date"]):
        moment = _date(sale["date"])
        line = lines.get(sale["lot"])
        quantity = float(sale["quantity"])
        if line:
            line.sold_total += quantity
            line.sales += 1
            line.last_sale = moment
            if moment and moment >= today - timedelta(days=30):
                line.sold_30 += quantity
            if moment and moment >= month_start:
                line.sold_month += quantity
        if moment and month_start <= moment <= today + timedelta(days=1):
            by_product[sale["product"]][0] += quantity
            by_product[sale["product"]][1] = sale.get("unit", "")
    s.month_by_product = {k: (round(v[0], 2), v[1]) for k, v in sorted(by_product.items(), key=lambda item: -item[1][0])}
    s.last_sales = sorted(ctx.get("sales") or [], key=lambda x: x["date"], reverse=True)[:5]

    for line in lines.values():
        if line.status == "pending":
            s.pending.append(line)
        elif line.status in ("rejected", "cancelled"):
            s.refused.append(line)
        elif line.remaining > 0:
            s.stock.append(line)
        else:
            s.sold_out.append(line)
    s.stock.sort(key=lambda l: (l.days_left is None, l.days_left if l.days_left is not None else 0))
    return s


def expiry(line: Line) -> str:
    if line.days_left is None:
        return "date limite non renseignée"
    if line.days_left < 0:
        return f"date limite dépassée depuis {-line.days_left} jour(s) ({fmt.date(line.expiration.isoformat())})"
    if line.days_left == 0:
        return f"expire aujourd'hui ({fmt.date(line.expiration.isoformat())})"
    return f"expire dans {line.days_left} jour(s), le {fmt.date(line.expiration.isoformat())}"


def describe(line: Line) -> str:
    sold = f"vendu {fmt.quantity(round(line.sold_total, 2), line.unit)} au total, {fmt.quantity(round(line.sold_30, 2), line.unit)} sur 30 jours"
    last = f", dernière vente le {fmt.date(line.last_sale.isoformat())}" if line.last_sale else ", aucune vente"
    return (f"{line.lot} {line.product} : reste {fmt.quantity(line.remaining, line.unit)} sur {fmt.quantity(line.quantity, line.unit)}"
            f" ; {expiry(line)} ; {sold}{last} ; reçu le {fmt.date(line.received.isoformat()) if line.received else '?'} de {line.sender}")


def render(ctx: dict[str, Any]) -> str:
    s = summarize(ctx)
    d = s.distributor
    out = [f"DISTRIBUTEUR : {d.get('name', '?')} ({d.get('city') or 'ville inconnue'}). Aujourd'hui : {fmt.date(s.today.isoformat())}. Mois en cours : {s.month}."]

    out.append(f"\nRÉCEPTIONS EN ATTENTE ({len(s.pending)}) :")
    out += [f"- {l.lot} {l.product}, {fmt.quantity(l.quantity, l.unit)}, envoyé par {l.sender} ; {expiry(l)}" for l in s.pending] or ["Aucune."]

    shown = s.stock[:MAX_LOTS]
    out.append(f"\nSTOCK EN RAYON ({len(s.stock)} lots{', les ' + str(MAX_LOTS) + ' plus urgents affichés' if len(s.stock) > MAX_LOTS else ''}) :")
    out += [f"{i}. {describe(l)}" for i, l in enumerate(shown, start=1)] or ["Aucun lot en stock."]

    totals: dict[str, list] = defaultdict(lambda: [0.0, ""])
    for l in s.stock:
        totals[l.product][0] += l.remaining
        totals[l.product][1] = l.unit
    out.append("STOCK PAR PRODUIT : " + (" ; ".join(f"{p} {fmt.quantity(round(q, 2), u)}" for p, (q, u) in totals.items()) or "aucun") + ".")

    out.append(f"\nVENTES DU MOIS ({s.month}) : " + (" ; ".join(f"{p} {fmt.quantity(q, u)}" for p, (q, u) in s.month_by_product.items()) or "aucune vente ce mois-ci") + ".")
    out.append("DERNIÈRES VENTES : " + (" ; ".join(f"{fmt.date(x['date'])} {x['lot']} {x['product']} {fmt.quantity(float(x['quantity']), x.get('unit', ''))}" for x in s.last_sales) or "aucune") + ".")

    out.append(f"\nLOTS QUI EXPIRENT DANS LES {SOON_DAYS} JOURS : " + (" ; ".join(f"{l.lot} {l.product} ({expiry(l)}, reste {fmt.quantity(l.remaining, l.unit)})" for l in s.expiring) or "aucun") + ".")
    out.append("LOTS PÉRIMÉS ENCORE EN STOCK : " + (" ; ".join(f"{l.lot} {l.product} (reste {fmt.quantity(l.remaining, l.unit)})" for l in s.expired) or "aucun") + ".")
    slow, best, promo = s.slowest, s.best, s.promotion
    out.append("LOT QUI SE VEND LE MOINS (30 jours, rapporté à la quantité reçue) : " + (f"{slow.lot} {slow.product} ({fmt.quantity(round(slow.sold_30, 2), slow.unit)} vendus sur {fmt.quantity(slow.quantity, slow.unit)})" if slow else "pas assez de recul") + ".")
    out.append("LOT QUI SE VEND LE MIEUX (30 jours) : " + (f"{best.lot} {best.product} ({fmt.quantity(round(best.sold_30, 2), best.unit)} vendus sur {fmt.quantity(best.quantity, best.unit)})" if best else "aucune vente sur 30 jours") + ".")
    out.append("LOT À METTRE EN PROMOTION : " + (f"{promo.lot} {promo.product} ({expiry(promo)}, reste {fmt.quantity(promo.remaining, promo.unit)})" if promo else "aucun pour l'instant") + ".")
    out.append(f"LOTS ÉPUISÉS ({len(s.sold_out)}) : " + (", ".join(f"{l.lot} {l.product}" for l in s.sold_out[:15]) or "aucun") + ".")
    out.append(f"RÉCEPTIONS REFUSÉES OU ANNULÉES ({len(s.refused)}) : " + (", ".join(f"{l.lot} {l.product}" for l in s.refused[:10]) or "aucune") + ".")
    return "\n".join(out)
