<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Production;
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

        return view('admin.dashboard', [
            'pendingAccounts' => User::pendingApproval()->count(),
            'totalProducts'   => Product::count(),
            'totalProductions' => Production::count(),
            'totalUsers'      => $usersByRole->sum(),
            'latestPending'   => User::pendingApproval()->with('organization')->latest()->limit(5)->get(),
            'roleChart' => [
                'labels' => array_map(fn (UserRole $role) => $role->label(), UserRole::cases()),
                'values' => array_map(fn (UserRole $role) => (int) ($usersByRole[$role->value] ?? 0), UserRole::cases()),
            ],
        ]);
    }
}
