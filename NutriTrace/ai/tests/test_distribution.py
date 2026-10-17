import random

import pytest

from nutritrace_ai import certificates, reception, stock, stock_qa
from nutritrace_ai import synth_distribution as dsynth

GENUINE = {
    "id": 1, "today": "2026-10-17", "name": "Agriculture biologique", "type": "BIO", "issuer": "Ecocert",
    "number": "TN-BIO-2025-0412", "issue_date": "2025-03-01", "expiration_date": "2027-02-28", "status": "PENDING",
    "owner": {"name": "Domaine Chaâl", "organization": "Domaine Chaâl", "verified": True},
    "target": {"kind": "produit", "name": "Olives Chemlali", "production_methods": ["biologique"]},
    "duplicates": 0,
    "document": {"present": True, "mime": "application/pdf", "text": "ECOCERT\nCertificat n° TN-BIO-2025-0412\nDélivré à : Domaine Chaâl\n"
                 "Produit(s) couvert(s) : Olives Chemlali\nRéférentiel : Agriculture biologique\nDate de délivrance : 01/03/2025"},
}


def with_document(**changes):
    return {**GENUINE, "document": {**GENUINE["document"], **changes}}


@pytest.mark.parametrize("certificate, level, reason", [
    (GENUINE, "faible", "Organisme reconnu"),
    ({**GENUINE, "issuer": "Ecocerts"}, "eleve", "ressemble à « Ecocert »"),
    ({**GENUINE, "issuer": "Green Label Africa"}, "moyen", "ne figure pas parmi les organismes reconnus"),
    ({**GENUINE, "duplicates": 2}, "moyen", "déjà utilisé par 2"),
    ({**with_document(text=GENUINE["document"]["text"].replace("TN-BIO-2025-0412", "12345")), "number": "12345"}, "moyen", "ne suit pas le format"),
    ({**GENUINE, "document": {"present": False}}, "moyen", "Aucun justificatif"),
    (with_document(text=GENUINE["document"]["text"].replace("TN-BIO-2025-0412", "TN-BIO-2025-9999")), "moyen", "n'apparaît pas dans le justificatif"),
    ({**GENUINE, "target": {**GENUINE["target"], "production_methods": ["conventionnelle"]}}, "moyen", "méthode conventionnelle"),
    ({**GENUINE, "number": None, "document": {"present": False}}, "eleve", "Aucun numéro"),
])
def test_certificate_rules(certificate, level, reason):
    assert certificates.rule_level(certificate) == level
    assert any(reason in r for r in certificates.reasons(certificate, level)), certificates.reasons(certificate, level)
    assert len(certificates.vector(certificate)) == len(certificates.FEATURE_NAMES)


def test_local_labels_accept_professional_bodies():
    local = {**GENUINE, "type": "LOCAL", "issuer": "Groupement des viticulteurs de Mornag", "number": "GVM-2026-014",
             "document": with_document(text=GENUINE["document"]["text"].replace("ECOCERT", "Groupement des viticulteurs de Mornag")
                                       .replace("TN-BIO-2025-0412", "GVM-2026-014").replace("Agriculture biologique", "Produit local"))["document"]}
    assert certificates.issuer_match(local["issuer"], "LOCAL")["recognized"]
    assert certificates.rule_level(local) == "faible"


RECEPTION = {
    "today": "2026-10-17",
    "lot": {"number": "LOT-2026-001", "product": "Huile d'olive", "unit": "L", "quantity": 100, "production_date": "2026-01-10", "expiration_date": "2027-07-10"},
    "distribution": {"quantity": 100, "sender": "Huilerie Sidi Mansour"},
    "certifications": [{"name": "Agriculture biologique", "type": "BIO", "status": "VERIFIED", "valid": True}],
    "warnings": [], "chain_valid": True, "under_investigation": False, "transparency_score": 85, "transport": None,
}


@pytest.mark.parametrize("changes, decision, reason", [
    ({}, "accepter", "Date limite dans"),
    ({"lot": {**RECEPTION["lot"], "expiration_date": "2026-10-10"}}, "refuser", "dépassée depuis 7 jour"),
    ({"chain_valid": False}, "refuser", "modifié après coup"),
    ({"lot": {**RECEPTION["lot"], "production_date": "2026-10-01", "expiration_date": "2026-10-21"}}, "reserves", "Date limite proche"),
    ({"warnings": [{"severity": "high", "code": "bio_unverified", "message": "Mention « bio »..."}]}, "reserves", "ne l'affichez pas comme bio"),
    ({"under_investigation": True}, "reserves", "signalement"),
    ({"certifications": [{"name": "Commerce équitable", "status": "REJECTED", "valid": False}]}, "reserves", "a été refusé"),
])
def test_reception_rules(changes, decision, reason):
    payload = {**RECEPTION, **changes}
    assert reception.rule_decision(payload) == decision
    assert any(reason in r for r in reception.reasons(payload, decision)), reception.reasons(payload, decision)


def test_a_transport_anomaly_is_a_reservation():
    assert reception.rule_decision(RECEPTION, transport_label="distance_inflated") == "reserves"
    assert any("Anomalie détectée sur le transport" in r for r in reception.reasons(RECEPTION, "reserves", "distance_inflated"))


def test_stock_summary():
    ctx = {
        "today": "2026-10-17", "distributor": {"name": "Marché Vert", "city": "Tunis"},
        "distributions": [
            {"lot": "L1", "product": "Fromage", "unit": "kg", "status": "in_store", "quantity": 100, "remaining": 80, "expiration_date": "2026-10-20", "received_at": "2026-09-01", "sender": "A"},
            {"lot": "L2", "product": "Huile", "unit": "L", "status": "in_store", "quantity": 100, "remaining": 10, "expiration_date": "2027-10-20", "received_at": "2026-09-01", "sender": "B"},
            {"lot": "L3", "product": "Dattes", "unit": "kg", "status": "pending", "quantity": 50, "remaining": 50, "expiration_date": "2027-01-01", "sender": "C"},
            {"lot": "L4", "product": "Miel", "unit": "kg", "status": "in_store", "quantity": 20, "remaining": 5, "expiration_date": "2026-10-01", "received_at": "2026-08-01", "sender": "D"},
        ],
        "sales": [
            {"lot": "L1", "product": "Fromage", "unit": "kg", "quantity": 20, "date": "2026-10-05T10:00:00"},
            {"lot": "L2", "product": "Huile", "unit": "L", "quantity": 90, "date": "2026-10-10T10:00:00"},
            {"lot": "L4", "product": "Miel", "unit": "kg", "quantity": 15, "date": "2026-08-10T10:00:00"},
        ],
    }
    s = stock.summarize(ctx)
    assert [l.lot for l in s.expiring] == ["L1"] and [l.lot for l in s.expired] == ["L4"]
    assert [l.lot for l in s.pending] == ["L3"]
    assert s.best.lot == "L2" and s.slowest.lot == "L1" and s.promotion.lot == "L1"
    assert s.month_by_product == {"Huile": (90.0, "L"), "Fromage": (20.0, "kg")}
    text = stock.render(ctx)
    assert "LOTS QUI EXPIRENT DANS LES 7 JOURS : L1 Fromage" in text and "Mois en cours : octobre 2026" in text


@pytest.mark.parametrize("intent", list(stock_qa.INTENTS))
def test_every_stock_answer_contains_its_checks(intent):
    rng = random.Random(intent)
    _, build, _ = stock_qa.INTENTS[intent]
    for _ in range(80):
        ctx = dsynth.distributor(rng)
        s = stock.summarize(ctx)
        products = sorted({l.product for l in s.stock} | set(s.month_by_product)) or ["Huile"]
        lots = [l.lot for l in s.stock + s.pending + s.sold_out] or ["LOT-2026-001"]
        answer, checks = build(ctx, s, rng, product=rng.choice(products), lot=rng.choice(lots))
        assert answer.strip()
        for check in checks:
            assert check in answer, (intent, check, answer)


def test_synthetic_data_covers_every_label():
    rng = random.Random(1)
    assert {certificates.rule_level(dsynth.certificate(rng)) for _ in range(500)} == set(certificates.LEVELS)
    assert {reception.rule_decision(dsynth.reception(rng)) for _ in range(500)} == set(reception.DECISIONS)
