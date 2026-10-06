<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.edit');
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $audit->log('settings.updated', null, __('admin.settings.title'));

        return back()->with('success', __('admin.settings.updated'));
    }

    public function reset(AuditLogger $audit): RedirectResponse
    {
        $this->settings->reset();
        $audit->log('settings.reset', null, __('admin.settings.title'));

        return back()->with('success', __('admin.settings.reset_done'));
    }
}
