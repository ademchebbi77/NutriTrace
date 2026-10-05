<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\EnvironmentalImpact;
use App\Models\Lot;
use App\Models\Report;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $usersByRole = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $grades = EnvironmentalImpact::query()
            ->whereNotNull('grade')
            ->selectRaw('grade, count(*) as total')
            ->groupBy('grade')
            ->pluck('total', 'grade');

        return view('admin.dashboard', [
            'pendingAccounts' => User::pendingApproval()->count(),
            'pendingCertifications' => Certification::where('status', CertificationStatus::PENDING)->count(),
            'openReports' => Report::open()->count(),
            'totalLots' => Lot::count(),
            'totalUsers' => $usersByRole->sum(),
            'averageTrust' => (int) round((float) Lot::whereNotNull('trust_score')->avg('trust_score')),
            'latestPending' => User::pendingApproval()->with('organization')->latest()->limit(5)->get(),
            'latestReports' => Report::open()->with('reportable')->latest()->limit(5)->get(),
            'roleChart' => [
                'labels' => array_map(fn (UserRole $role) => $role->label(), UserRole::cases()),
                'values' => array_map(fn (UserRole $role) => (int) ($usersByRole[$role->value] ?? 0), UserRole::cases()),
            ],
            'gradeChart' => [
                'labels' => ['A', 'B', 'C', 'D', 'E'],
                'values' => array_map(fn (string $grade) => (int) ($grades[$grade] ?? 0), ['A', 'B', 'C', 'D', 'E']),
            ],
        ]);
    }
}
