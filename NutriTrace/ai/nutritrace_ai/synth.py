"""Synthetic lots and transports in the exact format of the Laravel export.

The 57 seeded lots are too few to fine-tune a model: these varied but realistic
journeys (Tunisian places, products and actors, same French event sentences as
lang/fr/events.php) teach it to read any lot rather than memorise the demo ones.
"""

import random
from datetime import datetime, timedelta
from typing import Any

from . import formatting as fmt
from .config import EMISSION_FACTORS, LOCAL_RADIUS_KM
from .geo import haversine_km

PLACES = {
    "Tunis": (36.8065, 10.1815), "Sfax": (34.7406, 10.7603), "Sousse": (35.8256, 10.6084), "Nabeul": (36.4561, 10.7376),
    "Bizerte": (37.2744, 9.8739), "Gabès": (33.8815, 10.0982), "Kairouan": (35.6781, 10.0963), "Béja": (36.7256, 9.1817),
    "Jendouba": (36.5011, 8.7802), "Monastir": (35.7643, 10.8113), "Mahdia": (35.5047, 11.0622), "Tozeur": (33.9197, 8.1335),
    "Kasserine": (35.1676, 8.8365), "Médenine": (33.3549, 10.5055), "Zaghouan": (36.4029, 10.1429), "Siliana": (36.0849, 9.3708),
    "Le Kef": (36.1826, 8.7149), "Gafsa": (34.4250, 8.7842), "Sidi Bouzid": (35.0382, 9.4849), "Ariana": (36.8625, 10.1956),
    "Ben Arous": (36.7531, 10.2189), "Kébili": (33.7044, 8.9690), "Djerba": (33.8758, 10.8575), "Korba": (36.5786, 10.8586),
    "Tataouine": (32.9297, 10.4518), "Hammamet": (36.4000, 10.6167), "Testour": (36.5513, 9.4431), "Mateur": (37.0400, 9.6650),
}
ABROAD = {"Marseille": (43.2965, 5.3698), "Paris": (48.8566, 2.3522), "Rome": (41.9028, 12.4964), "Lyon": (45.7640, 4.8357), "Milan": (45.4642, 9.1900)}

# name, category, unit, (min, max) quantity, origin regions, (transformed product, unit, yield, plant) or None
PRODUCTS = [
    ("Olives Chemlali", "Huiles et olives", "kg", (800, 6000), ["Sfax", "Sousse", "Mahdia", "Zaghouan"], ("Huile d'olive extra vierge", "L", 0.18, "Huilerie")),
    ("Piments Baklouti", "Fruits et légumes", "kg", (300, 1500), ["Nabeul", "Korba", "Kairouan"], ("Harissa traditionnelle", "kg", 0.7, "Conserverie")),
    ("Blé dur Karim", "Céréales", "t", (5, 40), ["Béja", "Jendouba", "Siliana", "Le Kef"], ("Couscous fin", "kg", 700, "Minoterie")),
    ("Lait de vache", "Produits laitiers", "L", (500, 4000), ["Béja", "Mateur", "Bizerte"], ("Fromage frais", "kg", 0.15, "Laiterie")),
    ("Tomates de plein champ", "Fruits et légumes", "kg", (500, 8000), ["Kairouan", "Sidi Bouzid", "Nabeul"], ("Concentré de tomate", "kg", 0.17, "Conserverie")),
    ("Dattes Deglet Nour", "Fruits secs et dattes", "kg", (300, 5000), ["Tozeur", "Kébili", "Gafsa"], None),
    ("Oranges maltaises", "Fruits et légumes", "kg", (1000, 9000), ["Nabeul", "Korba", "Hammamet"], None),
    ("Miel de thym", "Miels", "kg", (50, 600), ["Zaghouan", "Siliana", "Kasserine"], None),
    ("Amandes", "Fruits secs et dattes", "kg", (200, 2500), ["Sfax", "Sidi Bouzid", "Kasserine"], None),
    ("Grenades de Gabès", "Fruits et légumes", "kg", (300, 4000), ["Gabès", "Testour"], None),
    ("Pommes de terre", "Fruits et légumes", "kg", (1000, 10000), ["Jendouba", "Bizerte", "Nabeul"], None),
    ("Figues de Barbarie", "Fruits et légumes", "kg", (200, 3000), ["Kasserine", "Sidi Bouzid"], None),
]
FARMS = ["Ferme", "Domaine", "Coopérative", "Maraîchers de", "Vergers de", "Rucher de", "Exploitation"]
FARM_NAMES = ["El Amel", "Ben Salah", "Chaâl", "Errahma", "Sidi Mansour", "Ennour", "Zitouna", "El Baraka", "Les Oasis", "Dar Jedd", "Oued Mellegue", "Cap Bon"]
SHOPS = ["Épicerie fine", "Souk Bio", "Marché central de", "Magasin", "Supérette", "Coopérative de consommateurs de"]
METHODS = ["conventionnelle", "biologique", "raisonnée", "permaculture"]
CERTIFICATIONS = [
    ("Agriculture biologique", "Ecocert"), ("Sécurité des denrées alimentaires ISO 22000", "INNORPI"),
    ("Commerce équitable", "Fairtrade"), ("Produit du terroir", "APIA"), ("Agriculture durable", "CTAB"),
]
TRUST_LEVELS = [(75, "Transparence élevée"), (50, "Transparence partielle"), (0, "Transparence faible")]
WARNINGS = {
    "unverified": ("low", "Acteur(s) dont l'organisation n'a pas été vérifiée."),
    "bio": ("high", "Mention « bio » sans certificat biologique vérifié."),
    "pending": ("medium", "Certificat en cours de vérification : il n'est pas encore pris en compte."),
    "no_env": ("medium", "Aucune donnée environnementale chiffrée : l'empreinte ne peut pas être évaluée."),
    "no_distance": ("medium", "Transport sans distance renseignée."),
    "chain": ("high", "Le journal de traçabilité a été modifié après coup : son intégrité n'est plus garantie."),
    "investigation": ("high", "Un signalement concernant ce lot est en cours d'examen."),
}
UNIT_KG = {"kg": 1.0, "L": 1.0, "t": 1000.0, "unité": None}


class Builder:
    """Accumulates the events and transports of one synthetic journey."""

    def __init__(self, rng: random.Random):
        self.rng = rng
        self.events: list[dict[str, Any]] = []
        self.transports: list[dict[str, Any]] = []
        self.counter = rng.randint(1, 900)
        self.year = rng.choice([2025, 2026])

    def lot_number(self) -> str:
        self.counter += self.rng.randint(1, 7)
        return f"LOT-{self.year}-{self.counter:03d}"

    def event(self, kind: str, at: datetime, description: str, location: str, actor: str, lot: str) -> None:
        self.events.append({"type": kind, "lot": lot, "occurred_at": at.isoformat(), "description": description, "location": location, "actor": actor})

    def actor(self, kinds: list[str], town: str, abroad: bool = False) -> dict[str, Any]:
        coords = (ABROAD if abroad else PLACES)[town]
        name = f"{self.rng.choice(kinds)} {town if kinds is SHOPS else self.rng.choice(FARM_NAMES)}"
        jitter = 0 if abroad else 0.03
        return {"name": name, "town": town, "lat": coords[0] + self.rng.uniform(-jitter, jitter), "lng": coords[1] + self.rng.uniform(-jitter, jitter)}

    def travel(self, lot: str, production_date: datetime, origin: dict, destination: dict, leave: datetime,
               quantity: float, unit: str, status: str = "delivered", international: bool = False) -> tuple[dict, datetime]:
        rng = self.rng
        straight = haversine_km(origin["lat"], origin["lng"], destination["lat"], destination["lng"])
        if international:
            mode = rng.choice(["ship", "plane", "ship"])
        else:
            mode = rng.choices(["truck", "van", "train"], weights=[60, 32, 8])[0]
        roll = rng.random()
        distance = None if roll < 0.05 else round(straight, 2) if roll < 0.45 else round(straight * rng.uniform(1.05, 1.45), 2)
        speed = {"truck": rng.uniform(40, 75), "van": rng.uniform(40, 80), "train": rng.uniform(50, 90), "ship": rng.uniform(20, 35), "plane": rng.uniform(300, 600)}[mode]
        arrival = leave + timedelta(hours=(distance or straight) / speed + rng.uniform(0.2, 4))
        mass = quantity * UNIT_KG[unit] if UNIT_KG.get(unit) else None
        co2 = round(distance * mass / 1000 * EMISSION_FACTORS[mode], 2) if distance is not None and mass else None

        transport = {
            "lot": lot, "production_date": production_date.date().isoformat(), "mode": mode, "mode_label": fmt.mode(mode),
            "status": status, "status_label": {"delivered": "Livré", "in_transit": "En route", "cancelled": "Annulé"}[status],
            "origin": f"{origin['name']}, {origin['town']}", "origin_latitude": round(origin["lat"], 7), "origin_longitude": round(origin["lng"], 7),
            "destination": f"{destination['name']}, {destination['town']}", "destination_latitude": round(destination["lat"], 7),
            "destination_longitude": round(destination["lng"], 7), "departure_date": leave.isoformat(),
            "arrival_date": arrival.isoformat() if status == "delivered" else None, "distance_km": distance,
            "quantity": quantity, "unit": unit, "mass_kg": mass, "co2_kg": co2,
        }
        self.transports.append(transport)
        self.event("TRANSPORT", leave, f"Départ vers {transport['destination']} en {fmt.mode(mode).lower()} ({'?' if distance is None else fmt.number(distance)} km).",
                   transport["origin"], origin["name"], lot)
        if status == "delivered":
            self.event("TRANSPORT", arrival, f"Arrivée à {transport['destination']}.", transport["destination"], destination["name"], lot)
        elif status == "cancelled":
            self.event("TRANSPORT", arrival, f"Livraison refusée par {transport['destination']} : retour à l'expéditeur.", transport["origin"], destination["name"], lot)
        return transport, arrival


def lot(rng: random.Random) -> dict[str, Any]:
    """One synthetic lot context, as AiContext::forLot() would return it."""
    b = Builder(rng)
    name, category, unit, (low, high), regions, transformed = rng.choice(PRODUCTS)
    scenario = rng.choices(["farm", "direct", "transformed", "in_transit", "refused", "export"], weights=[12, 33, 33, 10, 6, 6])[0]
    if scenario == "transformed" and not transformed:
        scenario = "direct"

    farm = b.actor(FARMS, rng.choice(regions))
    produced = datetime(b.year, rng.randint(1, 12), rng.randint(1, 28), 0, 0)
    quantity = float(rng.randint(low, high)) if unit != "t" else float(rng.randint(low, high))
    farm_lot = b.lot_number()
    method = rng.choice(METHODS)
    b.event("PRODUCTION", produced, f"Production de {fmt.quantity(quantity, unit)} de {name} (méthode {method}).",
            f"{farm['name']}, {farm['town']}", farm["name"], farm_lot)

    current = {"number": farm_lot, "name": name, "unit": unit, "quantity": quantity, "produced": produced, "holder": farm}
    sources: list[str] = []
    status_label, remaining = "Créé", quantity
    final_destination = None
    when = produced + timedelta(days=rng.randint(1, 3), hours=rng.randint(6, 9))

    if scenario == "transformed":
        product_name, out_unit, ratio, kind = transformed
        plant = b.actor([kind], rng.choice(list(PLACES)))
        _, arrived = b.travel(farm_lot, produced, farm, plant, when, quantity, unit)
        out_quantity = round(quantity * ratio, 0)
        made = arrived + timedelta(days=rng.randint(1, 10))
        made = made.replace(hour=0, minute=0)
        new_lot = b.lot_number()
        b.event("TRANSFORMATION", made, f"Transformation : obtention de {fmt.quantity(out_quantity, out_unit)} de {product_name}.",
                f"{plant['name']}, {plant['town']}", plant["name"], new_lot)
        b.event("TRANSFORMATION", made, f"{fmt.quantity(quantity, unit)} utilisés pour produire {product_name} (lot {new_lot}).",
                f"{plant['name']}, {plant['town']}", plant["name"], farm_lot)
        sources = [farm_lot]
        name, unit, quantity, category = product_name, out_unit, out_quantity, category
        current = {"number": new_lot, "name": name, "unit": unit, "quantity": quantity, "produced": made, "holder": plant}
        remaining, status_label = quantity, "Créé"
        when = made + timedelta(days=rng.randint(1, 6), hours=rng.randint(6, 9))

    if scenario in ("direct", "transformed", "in_transit", "refused", "export"):
        abroad = scenario == "export"
        shop = b.actor(SHOPS, rng.choice(list(ABROAD if abroad else PLACES)), abroad=abroad)
        status = {"in_transit": "in_transit", "refused": "cancelled"}.get(scenario, "delivered")
        _, arrived = b.travel(current["number"], current["produced"], current["holder"], shop, when, current["quantity"], unit, status, international=abroad)
        place = f"{shop['name']}, {shop['town']}"
        b.event("DISTRIBUTION", when, f"Expédition de {fmt.quantity(current['quantity'], unit)} au distributeur {shop['name']}.", place, current["holder"]["name"], current["number"])
        if status == "delivered":
            final_destination = shop
            b.event("DISTRIBUTION", arrived, f"Réception confirmée par {shop['name']} ({place}).", place, shop["name"], current["number"])
            status_label = "Distribué"
            if rng.random() < 0.75:
                b.event("DISTRIBUTION", arrived + timedelta(hours=rng.randint(4, 30)), f"Mise en rayon : {place}.", place, shop["name"], current["number"])
                status_label = "En magasin"
                for _ in range(rng.randint(0, 3)):
                    sold = round(current["quantity"] * rng.uniform(0.05, 0.35), 0)
                    if sold >= remaining:
                        b.event("SALE", arrived + timedelta(days=rng.randint(2, 20)), f"Vente de {fmt.quantity(remaining, unit)} ({place}) : lot épuisé.", place, shop["name"], current["number"])
                        remaining, status_label = 0.0, "Épuisé"
                        break
                    remaining -= sold
                    b.event("SALE", arrived + timedelta(days=rng.randint(2, 20)), f"Vente de {fmt.quantity(sold, unit)} ({place}).", place, shop["name"], current["number"])
        elif status == "in_transit":
            status_label = "En transit"
        else:
            b.event("DISTRIBUTION", arrived, f"Lot refusé par le distributeur {shop['name']}.", place, shop["name"], current["number"])

    b.events.sort(key=lambda e: e["occurred_at"])
    return finish(rng, b, current, name, category, unit, remaining, status_label, sources, farm, final_destination)


def finish(rng, b: Builder, current, name, category, unit, remaining, status_label, sources, farm, final_destination) -> dict[str, Any]:
    active = [t for t in b.transports if t["status"] != "cancelled"]
    food_miles = round(sum(t["distance_km"] or 0 for t in active), 2)
    has_env = rng.random() > 0.1
    score = rng.randint(15, 95)
    grade = "A" if score >= 80 else "B" if score >= 60 else "C" if score >= 40 else "D" if score >= 20 else "E"

    local = {"is_local": None, "distance_km": None, "radius_km": float(LOCAL_RADIUS_KM)}
    if final_destination:
        far = round(haversine_km(farm["lat"], farm["lng"], final_destination["lat"], final_destination["lng"]), 2)
        local = {"is_local": far <= LOCAL_RADIUS_KM, "distance_km": far, "radius_km": float(LOCAL_RADIUS_KM)}

    certifications = []
    for cert, body in rng.sample(CERTIFICATIONS, rng.choices([0, 1, 2], weights=[40, 40, 20])[0]):
        valid = rng.random() < 0.7
        certifications.append({"name": cert, "valid": valid, "issuing_organization": body,
                               "expiration_date": f"{b.year + rng.choice([-1, 1, 2])}-{rng.randint(1, 12):02d}-{rng.randint(1, 28):02d}"})

    chain_valid = rng.random() > 0.05
    investigation = rng.random() < 0.06
    keys = [k for k, p in (("unverified", 0.5), ("bio", 0.12), ("pending", 0.15)) if rng.random() < p]
    if not has_env:
        keys.append("no_env")
    if any(t["distance_km"] is None for t in active):
        keys.append("no_distance")
    if not chain_valid:
        keys.append("chain")
    if investigation:
        keys.append("investigation")
    trust = max(5, min(100, rng.randint(40, 95) - 12 * len([k for k in keys if WARNINGS[k][0] == "high"])))

    return {
        "lot": {"number": current["number"], "status_label": status_label, "quantity": remaining, "initial_quantity": current["quantity"],
                "production_date": current["produced"].date().isoformat(),
                "expiration_date": (current["produced"] + timedelta(days=rng.choice([10, 30, 180, 365, 730]))).date().isoformat() if rng.random() < 0.8 else None,
                "unit": unit},
        "product": {"name": name, "category": category, "origin": f"{farm['town']}, Tunisie" if rng.random() < 0.8 else None},
        "journey": b.events,
        "source_lots": sources,
        "transports": b.transports,
        "chain": {"valid": chain_valid, "events": len(b.events)},
        "environmental_impact": {"grade": grade, "score": score, "food_miles_km": food_miles,
                                 "co2_per_kg": round(rng.uniform(0.05, 2.2), 3)} if has_env else None,
        "local": local,
        "certifications": certifications,
        "transparency": {"score": trust, "level": next(label for floor, label in TRUST_LEVELS if trust >= floor)},
        "warnings": [{"severity": WARNINGS[k][0], "message": WARNINGS[k][1]} for k in keys],
        "under_investigation": investigation,
    }


def normal_transports(rng: random.Random, count: int) -> list[dict[str, Any]]:
    """Plausible transports, taken from synthetic journeys."""
    out: list[dict[str, Any]] = []
    while len(out) < count:
        out.extend(t for t in lot(rng)["transports"] if t["distance_km"] is not None)
    return out[:count]


def inject(rng: random.Random, t: dict[str, Any], kind: str) -> dict[str, Any]:
    """Copy of a transport with one kind of anomaly."""
    t = dict(t)
    departure = fmt.parse_date(t["departure_date"])
    straight = haversine_km(t["origin_latitude"], t["origin_longitude"], t["destination_latitude"], t["destination_longitude"])

    if kind == "distance_inflated":
        t["distance_km"] = round(max(straight * rng.uniform(2.7, 9), rng.uniform(70, 600)), 2)
        # A longer trip takes longer: only the distance is wrong, not the speed.
        if t.get("arrival_date"):
            t["arrival_date"] = (departure + timedelta(hours=t["distance_km"] / rng.uniform(40, 70))).isoformat()
    elif kind == "distance_too_short":
        t["distance_km"] = round(straight * rng.uniform(0.1, 0.85), 2)
    elif kind == "speed_implausible":
        speed = {"truck": 90, "van": 100, "train": 160, "ship": 45, "plane": 800}[t["mode"]] * rng.uniform(1.4, 6)
        t["arrival_date"] = (departure + timedelta(hours=(t["distance_km"] or straight) / speed)).isoformat()
    elif kind == "mode_mismatch":
        t["mode"] = rng.choice(["plane", "plane", "ship"])
        t["mode_label"] = fmt.mode(t["mode"])
        limit = 150 if t["mode"] == "plane" else 30
        if straight * 1.1 >= limit:
            # Bring the destination closer so the short trip itself stays plausible.
            straight = rng.uniform(6, limit * 0.6)
            t["destination_latitude"] = round(t["origin_latitude"] + straight / 111.2, 7)
            t["destination_longitude"] = t["origin_longitude"]
            straight = haversine_km(t["origin_latitude"], t["origin_longitude"], t["destination_latitude"], t["destination_longitude"])
        t["distance_km"] = round(min(straight * rng.uniform(1.05, 1.4), limit * 0.95), 2)
        t["arrival_date"] = (departure + timedelta(hours=rng.uniform(2, 10))).isoformat()
    elif kind == "dates_inconsistent":
        if rng.random() < 0.5:
            t["arrival_date"] = (departure - timedelta(hours=rng.uniform(1, 72))).isoformat()
        else:
            t["production_date"] = (departure.date() + timedelta(days=rng.randint(1, 30))).isoformat()
    elif kind == "missing_distance":
        t["distance_km"] = None
    t["co2_kg"] = None if t["distance_km"] is None or not t.get("mass_kg") else round(t["distance_km"] * t["mass_kg"] / 1000 * EMISSION_FACTORS[t["mode"]], 2)
    return t
