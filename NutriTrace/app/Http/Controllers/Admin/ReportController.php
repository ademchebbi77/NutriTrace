<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\EnvironmentalImpact;
use App\Models\Lot;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Report::class);

        return view('admin.reports.index', [
            'reports' => Report::with(['user', 'reportable'])->latest()->get()
                // Open reports first.
                ->sortBy(fn (Report $report) => $report->status->isOpen() ? 0 : 1)
                ->values(),
        ]);
    }

    public function show(Report $report): View
    {
        Gate::authorize('view', $report);

        $report->load(['user', 'reportable', 'reviewer']);

        return view('admin.reports.show', [
            'report' => $report,
            'statuses' => [ReportStatus::UNDER_REVIEW, ReportStatus::RESOLVED, ReportStatus::REJECTED],
            'lot' => $this->relatedLot($report),
        ]);
    }

    /**
     * Put a report under review (which shows the banner on the public lot page),
     * resolve it or reject it.
     */
    public function update(Request $request, Report $report, ReportService $reports): RedirectResponse
    {
        Gate::authorize('moderate', $report);

        $data = $request->validate([
            'status' => ['required', Rule::in([ReportStatus::UNDER_REVIEW->value, ReportStatus::RESOLVED->value, ReportStatus::REJECTED->value])],
            // A closing decision must be explained to the person who reported.
            'admin_response' => ['nullable', 'required_unless:status,'.ReportStatus::UNDER_REVIEW->value, 'string', 'max:2000'],
        ], [], __('reports.attributes'));

        $reports->moderate($report, $request->user(), ReportStatus::from($data['status']), $data['admin_response'] ?? null);

        return redirect()->route('admin.reports.show', $report)->with('success', __('reports.admin.updated'));
    }

    private function relatedLot(Report $report): ?Lot
    {
        $target = $report->reportable;

        return match (true) {
            $target instanceof Lot => $target,
            $target instanceof EnvironmentalImpact => $target->lot,
            $target instanceof Certification && $target->certifiable instanceof Lot => $target->certifiable,
            default => null,
        };
    }
}
