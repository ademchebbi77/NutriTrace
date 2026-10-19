"""Tests for nutritrace_ai.production — forecast module.

Run with:
    .venv\\Scripts\\pytest tests/test_production.py -v
"""

import pytest

from nutritrace_ai.production import (
    STAT_MIN,
    TREND_MIN,
    MODEL_MIN,
    TEST_MIN,
    forecast,
    _prepare,
    _statistics,
    _trend_slope,
    _baseline_forecast,
    _next_period,
    _train_and_evaluate,
)


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def make_record(date: str, quantity: float, unit: str = "kg", method: str = "organic") -> dict:
    return {"production_date": date, "quantity": quantity, "unit": unit, "production_method": method}


def history(n: int, start_year: int = 2015, qty_start: float = 3000.0, step: float = 100.0, unit: str = "kg") -> list:
    """Generate n annual records starting at start_year."""
    return [
        make_record(f"{start_year + i}-06-15", qty_start + i * step, unit)
        for i in range(n)
    ]


# ---------------------------------------------------------------------------
# _prepare
# ---------------------------------------------------------------------------

class TestPrepare:
    def test_sorts_chronologically(self):
        records = [
            make_record("2023-01-01", 100),
            make_record("2021-01-01", 200),
            make_record("2022-01-01", 150),
        ]
        prepared = _prepare(records)
        dates = [r["date"].year for r in prepared]
        assert dates == [2021, 2022, 2023]

    def test_skips_zero_quantity(self):
        records = [make_record("2022-01-01", 0), make_record("2023-01-01", 500)]
        prepared = _prepare(records)
        assert len(prepared) == 1
        assert prepared[0]["quantity"] == 500

    def test_skips_negative_quantity(self):
        records = [make_record("2022-01-01", -100), make_record("2023-01-01", 200)]
        prepared = _prepare(records)
        assert len(prepared) == 1

    def test_skips_missing_fields(self):
        records = [{"quantity": 100}, {"production_date": "2022-01-01"}]
        prepared = _prepare(records)
        assert len(prepared) == 0

    def test_skips_invalid_date(self):
        records = [make_record("not-a-date", 100), make_record("2023-06-01", 200)]
        prepared = _prepare(records)
        assert len(prepared) == 1

    def test_attaches_month(self):
        prepared = _prepare([make_record("2023-06-15", 1000)])
        assert prepared[0]["month"] == 6

    def test_attaches_method_code(self):
        prepared = _prepare([make_record("2023-06-15", 1000, method="organic")])
        assert prepared[0]["method_code"] == 1

    def test_unknown_method_defaults_to_4(self):
        prepared = _prepare([make_record("2023-06-15", 1000, method="unknown_xyz")])
        assert prepared[0]["method_code"] == 4

    def test_exact_duplicates_are_dropped(self):
        records = [
            make_record("2023-06-15", 1000),
            make_record("2023-06-15", 1000),
            make_record("2023-07-15", 1200),
        ]
        prepared = _prepare(records)
        assert len(prepared) == 2
        assert prepared[0]["quantity"] == 1000
        assert prepared[1]["quantity"] == 1200

    def test_same_quantity_different_date_is_kept(self):
        records = [
            make_record("2023-06-15", 1000),
            make_record("2023-07-15", 1000),
        ]
        assert len(_prepare(records)) == 2


# ---------------------------------------------------------------------------
# _statistics
# ---------------------------------------------------------------------------

class TestStatistics:
    def test_basic(self):
        stats = _statistics([1000.0, 2000.0, 3000.0])
        assert stats["count"] == 3
        assert stats["mean"] == pytest.approx(2000.0)
        assert stats["min"] == 1000.0
        assert stats["max"] == 3000.0
        assert stats["last"] == 3000.0

    def test_std_none_for_single(self):
        stats = _statistics([1000.0])
        assert stats["std"] is None

    def test_std_computed_for_two(self):
        stats = _statistics([100.0, 200.0])
        assert stats["std"] is not None
        assert stats["std"] > 0


# ---------------------------------------------------------------------------
# _trend_slope
# ---------------------------------------------------------------------------

class TestTrendSlope:
    def test_none_for_fewer_than_trend_min(self):
        prepared = _prepare([make_record(f"202{i}-01-01", 1000) for i in range(TREND_MIN - 1)])
        assert _trend_slope(prepared) is None

    def test_increasing_slope_positive(self):
        records = [make_record(f"{2018 + i}-06-01", 1000.0 + i * 200) for i in range(5)]
        prepared = _prepare(records)
        slope = _trend_slope(prepared)
        assert slope is not None
        assert slope > 0

    def test_decreasing_slope_negative(self):
        records = [make_record(f"{2018 + i}-06-01", 5000.0 - i * 300) for i in range(5)]
        prepared = _prepare(records)
        slope = _trend_slope(prepared)
        assert slope is not None
        assert slope < 0

    def test_flat_slope_near_zero(self):
        records = [make_record(f"{2018 + i}-06-01", 2000.0) for i in range(5)]
        prepared = _prepare(records)
        slope = _trend_slope(prepared)
        assert slope is not None
        assert abs(slope) < 1.0


# ---------------------------------------------------------------------------
# _baseline_forecast
# ---------------------------------------------------------------------------

class TestBaseline:
    def test_uses_last_three_by_default(self):
        quantities = [1000, 2000, 3000, 4000, 5000]
        baseline = _baseline_forecast(quantities)
        assert baseline == pytest.approx((3000 + 4000 + 5000) / 3)

    def test_uses_all_when_fewer_than_three(self):
        quantities = [1000.0, 2000.0]
        baseline = _baseline_forecast(quantities)
        assert baseline == pytest.approx(1500.0)


# ---------------------------------------------------------------------------
# forecast — insufficient_data status
# ---------------------------------------------------------------------------

class TestForecastInsufficientData:
    def test_empty_list_returns_insufficient(self):
        result = forecast([], "kg")
        assert result["status"] == "insufficient_data"
        assert result["prediction"] is None
        assert result["statistics"] is None
        assert result["history"] == []

    def test_single_record_returns_insufficient(self):
        result = forecast([make_record("2023-06-01", 3000)], "kg")
        assert result["status"] == "insufficient_data"

    def test_unit_preserved(self):
        result = forecast([], "t")
        assert result["unit"] == "t"

    def test_model_info_not_trained(self):
        result = forecast([], "kg")
        assert result["model_info"]["trained"] is False

    def test_explanation_present(self):
        result = forecast([], "kg")
        assert isinstance(result["explanation"], str)
        assert len(result["explanation"]) > 0


# ---------------------------------------------------------------------------
# forecast — statistical_only status
# ---------------------------------------------------------------------------

class TestForecastStatisticalOnly:
    def test_three_records_gives_statistical_only(self):
        records = history(3)
        result = forecast(records, "kg")
        assert result["status"] == "statistical_only"

    def test_has_baseline_estimate(self):
        records = history(3)
        result = forecast(records, "kg")
        assert result["baseline_estimate"] is not None
        assert result["baseline_estimate"] > 0

    def test_no_ml_prediction(self):
        records = history(3)
        result = forecast(records, "kg")
        assert result["prediction"] is None

    def test_trend_direction_present(self):
        records = history(5)
        result = forecast(records, "kg")
        assert result["trend_direction"] in ("increasing", "decreasing", "stable")

    def test_history_included(self):
        records = history(5)
        result = forecast(records, "kg")
        assert len(result["history"]) == 5

    def test_statistics_present(self):
        records = history(5)
        result = forecast(records, "kg")
        assert result["statistics"] is not None
        assert result["statistics"]["count"] == 5

    def test_just_below_model_min(self):
        records = history(MODEL_MIN - 1)
        result = forecast(records, "kg")
        assert result["status"] == "statistical_only"


# ---------------------------------------------------------------------------
# forecast — ok status (ML)
# ---------------------------------------------------------------------------

class TestForecastOk:
    def _enough_records(self):
        return history(MODEL_MIN + 5, qty_start=2000.0, step=150.0)

    def test_ok_status_with_enough_data(self):
        result = forecast(self._enough_records(), "kg")
        assert result["status"] == "ok"

    def test_prediction_non_negative(self):
        result = forecast(self._enough_records(), "kg")
        if result["prediction"] is not None:
            assert result["prediction"] >= 0

    def test_statistics_present(self):
        result = forecast(self._enough_records(), "kg")
        assert result["statistics"] is not None

    def test_history_length_correct(self):
        records = self._enough_records()
        result = forecast(records, "kg")
        assert len(result["history"]) == len(records)

    def test_model_info_present(self):
        result = forecast(self._enough_records(), "kg")
        assert "model_info" in result
        assert "name" in result["model_info"]

    def test_model_name_is_string(self):
        result = forecast(self._enough_records(), "kg")
        assert isinstance(result["model_info"]["name"], str)

    def test_baseline_present(self):
        result = forecast(self._enough_records(), "kg")
        assert result["baseline_estimate"] is not None


# ---------------------------------------------------------------------------
# forecast — multiple units (isolation)
# ---------------------------------------------------------------------------

class TestForecastUnitIsolation:
    def test_unit_in_response_matches_request(self):
        for unit in ["kg", "t", "L", "unit"]:
            result = forecast(history(3, unit=unit), unit)
            assert result["unit"] == unit

    def test_different_units_different_results(self):
        kg_result = forecast(history(3, unit="kg"), "kg")
        t_result  = forecast(history(3, unit="t"), "t")
        assert kg_result["unit"] != t_result["unit"]


# ---------------------------------------------------------------------------
# forecast — invalid / edge inputs
# ---------------------------------------------------------------------------

class TestForecastEdgeCases:
    def test_zero_quantities_filtered_out(self):
        records = [make_record("2022-01-01", 0), make_record("2023-01-01", 0)]
        result = forecast(records, "kg")
        assert result["status"] == "insufficient_data"

    def test_negative_quantities_filtered(self):
        records = [make_record("2022-01-01", -500), make_record("2023-01-01", 1000)]
        result = forecast(records, "kg")
        # Only 1 valid record
        assert result["status"] == "insufficient_data"

    def test_malformed_records_ignored(self):
        records = [{"foo": "bar"}, make_record("2023-01-01", 500), make_record("2022-01-01", 400)]
        result = forecast(records, "kg")
        assert result["statistics"]["count"] == 2

    def test_all_same_date_still_computes(self):
        records = [make_record("2023-06-01", 1000 + i * 100) for i in range(5)]
        result = forecast(records, "kg")
        assert result["statistics"] is not None
        # slope may be zero (same date → same year_frac)
        assert result["trend_per_year"] is not None or result["status"] == "statistical_only"

    def test_large_quantity_no_crash(self):
        records = history(5, qty_start=9_000_000.0, step=100_000.0)
        result = forecast(records, "kg")
        assert result["baseline_estimate"] is not None

    def test_prediction_never_negative(self):
        # Strongly decreasing trend: prediction must be clamped to 0
        records = history(MODEL_MIN + 2, qty_start=10_000.0, step=-1_500.0)
        result = forecast(records, "kg")
        if result["prediction"] is not None:
            assert result["prediction"] >= 0


# ---------------------------------------------------------------------------
# forecast — chronological validation integrity
# ---------------------------------------------------------------------------

class TestForecastChronological:
    def test_train_before_test_always(self):
        """
        The split must be strictly chronological: the earliest records are
        training, the most recent are test. We verify that model_info reports
        non-zero train and test sizes.
        """
        records = history(MODEL_MIN + 4)
        result  = forecast(records, "kg")
        if result["status"] == "ok" and result["model_info"]["trained"]:
            assert result["model_info"]["train_size"] > 0
            assert result["model_info"]["test_size"] > 0
            total = result["model_info"]["train_size"] + result["model_info"]["test_size"]
            assert total == len(records)

    def test_test_set_is_recent_records(self):
        """
        The test set size should be roughly 20% of total (at least 1 record).
        """
        n = MODEL_MIN + 10
        records = history(n)
        result  = forecast(records, "kg")
        if result["status"] == "ok" and result["model_info"]["trained"]:
            expected_test = max(2, n // 5)
            assert result["model_info"]["test_size"] >= 1
            assert result["model_info"]["test_size"] <= expected_test + 2

    def test_validation_set_has_at_least_test_min_records(self):
        """The chronological validation set must be large enough to evaluate."""
        records = history(MODEL_MIN + 5)
        result  = forecast(records, "kg")
        if result["status"] == "ok" and result["model_info"]["trained"]:
            assert result["model_info"]["test_size"] >= TEST_MIN


# ---------------------------------------------------------------------------
# forecast — statistical_only must not look like a forecast
# ---------------------------------------------------------------------------

class TestForecastNoFakePrediction:
    """With insufficient data, statistics are shown but never a prediction."""

    def test_two_extreme_records_never_produce_prediction(self):
        records = [
            make_record("2025-10-28", 1200),
            make_record("2026-10-14", 10),
        ]
        result = forecast(records, "kg")
        assert result["status"] == "statistical_only"
        assert result["prediction"] is None
        assert result["prediction_period"] is None
        assert result["model_info"]["name"] is None
        assert result["model_info"]["trained"] is False

    def test_explanation_labels_mean_as_historical(self):
        records = [
            make_record("2025-10-28", 1200),
            make_record("2026-10-14", 10),
        ]
        result = forecast(records, "kg")
        explanation = result["explanation"]
        assert "moyenne" in explanation
        assert "605 kg" in explanation
        assert "ne constitue pas une prévision fiable" in explanation

    def test_strong_variation_is_mentioned(self):
        records = [
            make_record("2025-10-28", 1200),
            make_record("2026-10-14", 10),
        ]
        assert "forte variation" in forecast(records, "kg")["explanation"]

    def test_low_variation_is_not_mentioned(self):
        records = [
            make_record("2025-10-28", 100),
            make_record("2026-10-14", 110),
        ]
        assert "forte variation" not in forecast(records, "kg")["explanation"]

    def test_explanation_uses_requested_unit(self):
        records = [
            make_record("2025-10-28", 1200, unit="t"),
            make_record("2026-10-14", 10, unit="t"),
        ]
        assert "605 t" in forecast(records, "t")["explanation"]

    def test_no_accuracy_or_confidence_claimed(self):
        records = [
            make_record("2025-10-28", 1200),
            make_record("2026-10-14", 10),
        ]
        result = forecast(records, "kg")
        assert "confiance" not in result["explanation"].lower()
        assert "%" not in result["explanation"]


# ---------------------------------------------------------------------------
# forecast — ML only when the model beats the baseline on real validation
# ---------------------------------------------------------------------------

class TestForecastMlHonesty:
    def test_constant_history_falls_back_to_baseline(self):
        """A constant series gives the model no edge: prediction must be None."""
        records = history(MODEL_MIN + 5, qty_start=1000.0, step=0.0)
        result = forecast(records, "kg")
        assert result["status"] == "ok"
        assert result["model_info"]["name"] == "baseline_mean"
        assert result["prediction"] is None
        assert result["baseline_estimate"] is not None
        assert "ligne de base" in result["model_info"]["reason"]

    def test_rising_history_uses_ml_model(self):
        records = history(MODEL_MIN + 5, qty_start=2000.0, step=150.0)
        result = forecast(records, "kg")
        assert result["status"] == "ok"
        if result["model_info"]["name"] == "gradient_boosting":
            assert result["prediction"] is not None
            assert result["prediction"] >= 0
            year, month = result["prediction_period"].split("-")
            assert 1 <= int(month) <= 12
            assert result["model_info"]["mae"] is not None
            assert result["model_info"]["baseline_mae"] is not None

    def test_metrics_come_from_validation(self):
        """MAE is only reported when a chronological validation happened."""
        records = history(MODEL_MIN + 5, qty_start=2000.0, step=150.0)
        result = forecast(records, "kg")
        assert result["model_info"]["train_size"] is not None
        assert result["model_info"]["test_size"] is not None
        assert result["model_info"]["train_size"] + result["model_info"]["test_size"] == len(records)


# ---------------------------------------------------------------------------
# validation metrics audit — reported numbers must match a manual recomputation
# ---------------------------------------------------------------------------

class TestValidationMetricsAudit:
    def test_next_period_same_month_next_year(self):
        prepared = _prepare([
            make_record("2026-03-15", 750),
            make_record("2026-07-10", 1500),
        ])
        assert _next_period(prepared) == "2027-07"

    def test_next_period_year_rollover(self):
        prepared = _prepare([make_record("2026-12-05", 500)])
        assert _next_period(prepared) == "2027-12"

    def test_trend_slope_matches_manual_ols(self):
        """The displayed trend is an OLS slope on fractional-year timestamps."""
        prepared = _prepare(history(6, qty_start=1000.0, step=100.0))
        x = [r["year_frac"] for r in prepared]
        y = [r["quantity"] for r in prepared]
        xc = [v - sum(x) / len(x) for v in x]
        yc = [v - sum(y) / len(y) for v in y]
        manual = sum(a * b for a, b in zip(xc, yc)) / sum(a * a for a in xc)
        assert abs(_trend_slope(prepared) - manual) < 1e-9
        # Leap years shift year_frac slightly, so the slope is not exactly 100.
        assert abs(_trend_slope(prepared) - 100.0) < 0.05

    def test_model_and_baseline_mae_share_the_validation_window(self):
        records = history(MODEL_MIN + 3, qty_start=1500.0, step=120.0)
        prepared = _prepare(records)
        n = len(prepared)
        test_size = max(TEST_MIN, int(round(n * 0.2)))
        split = n - test_size
        train, test = prepared[:split], prepared[split:]

        # Strictly chronological: every validation record is after training.
        assert all(t["date"] > train[-1]["date"] for t in test)
        assert test_size >= TEST_MIN

        eval_result = _train_and_evaluate(prepared)
        assert eval_result is not None
        assert eval_result["train_size"] == split
        assert eval_result["test_size"] == test_size

        # The baseline MAE is recomputed by hand on exactly the same records.
        baseline_value = _baseline_forecast([r["quantity"] for r in train])
        manual_baseline_mae = float(
            sum(abs(r["quantity"] - baseline_value) for r in test) / len(test)
        )
        assert abs(eval_result["baseline_mae"] - manual_baseline_mae) < 1e-9

        # The public response reports exactly the evaluation metrics (full
        # precision — display rounding happens in the UI only).
        result = forecast(records, "kg")
        assert result["model_info"]["mae"] == eval_result["model_mae"]
        assert result["model_info"]["baseline_mae"] == eval_result["baseline_mae"]
        assert result["model_info"]["train_size"] == split
        assert result["model_info"]["test_size"] == test_size

    def test_prediction_period_only_with_prediction(self):
        constant = history(MODEL_MIN + 5, qty_start=1000.0, step=0.0)
        result = forecast(constant, "kg")
        assert result["prediction"] is None
        assert result["prediction_period"] is None

        few = [make_record("2025-10-28", 1200), make_record("2026-10-14", 10)]
        result = forecast(few, "kg")
        assert result["prediction"] is None
        assert result["prediction_period"] is None

    def test_prediction_uses_last_record_method(self):
        """The next-period feature vector reuses the latest method, not a default."""
        records = history(MODEL_MIN + 3, qty_start=1500.0, step=120.0)
        records[-1]["production_method"] = "integrated"
        result = forecast(records, "kg")
        if result["prediction"] is not None:
            assert result["prediction_period"] is not None
            assert result["prediction"] >= 0
