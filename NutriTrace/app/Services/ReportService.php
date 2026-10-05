<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReportService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{type: string, description: string}  $data
     */
    public function submit(User $user, Model $target, array $data): Report
    {
        $report = new Report($data);
        $report->user_id = $user->id;
        $report->reportable()->associate($target);
        $report->save();

        return $report;
    }

    /**
     * Move a report through moderation: under review, resolved or rejected.
     */
    public function moderate(Report $report, User $admin, ReportStatus $status, ?string $response): void
    {
        $report->forceFill([
            'status' => $status,
            'admin_response' => $response,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->log('report.'.strtolower($status->value), $report, $report->targetLabel());
    }
}
