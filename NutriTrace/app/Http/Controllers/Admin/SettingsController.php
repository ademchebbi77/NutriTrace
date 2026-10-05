<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Scoring\LotScoreManager;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Lets the admin adjust emission factors, thresholds and score weights.
 * Values are stored as overrides of config/footprint.php and config/trust.php.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly LotScoreManager $scores,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'footprint' => config('footprint'),
            'trust' => config('trust'),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $rules = [];

        // Every numeric leaf of both configuration files is editable, nothing else.
        foreach (SettingsRepository::SECTIONS as $section) {
            foreach (array_keys(Arr::dot(config($section))) as $key) {
                $rules["$section.$key"] = ['required', 'numeric', 'min:0', 'max:1000000'];
            }
        }

        $validated = $request->validate($rules, [], ['*' => __('admin.settings.value')]);

        $trustWeights = array_sum($validated['trust']['weights']);
        $footprintWeights = array_sum($validated['footprint']['weights']);

        if (abs($trustWeights - 100) > 0.001) {
            return back()->withInput()->withErrors(['trust.weights' => __('admin.settings.trust_weights_sum', ['sum' => $trustWeights])]);
        }

        if (abs($footprintWeights - 1) > 0.001) {
            return back()->withInput()->withErrors(['footprint.weights' => __('admin.settings.footprint_weights_sum', ['sum' => $footprintWeights])]);
        }

        foreach (SettingsRepository::SECTIONS as $section) {
            $this->settings->save($section, $this->toNumbers($validated[$section]));
        }

        $this->scores->refreshAll();
        $audit->log('settings.updated', null, __('admin.settings.title'));

        return back()->with('success', __('admin.settings.updated'));
    }

    public function reset(AuditLogger $audit): RedirectResponse
    {
        $this->settings->reset();

        // Reload the untouched values of the config files for the rest of this request.
        foreach (SettingsRepository::SECTIONS as $section) {
            config([$section => require config_path($section.'.php')]);
        }

        $this->scores->refreshAll();
        $audit->log('settings.reset', null, __('admin.settings.title'));

        return back()->with('success', __('admin.settings.reset_done'));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function toNumbers(array $values): array
    {
        return array_map(fn ($value) => is_array($value) ? $this->toNumbers($value) : (float) $value, $values);
    }
}
