<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Admin overrides of the scoring configuration, stored in the "settings" table
 * and merged over config/footprint.php and config/trust.php at boot.
 */
class SettingsRepository
{
    public const SECTIONS = ['footprint', 'trust'];

    private const CACHE_KEY = 'settings.overrides';

    /**
     * Merge the stored overrides into the runtime configuration.
     */
    public function apply(): void
    {
        foreach ($this->overrides() as $section => $values) {
            if (in_array($section, self::SECTIONS, true) && is_array($values)) {
                config([$section => array_replace_recursive(config($section, []), $values)]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(string $section, array $values): void
    {
        Setting::updateOrCreate(['key' => $section], ['value' => $values]);

        Cache::forget(self::CACHE_KEY);
        $this->apply();
    }

    /**
     * Drop every override and go back to the values of the config files.
     */
    public function reset(): void
    {
        Setting::query()->whereIn('key', self::SECTIONS)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function overrides(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            // The table does not exist yet (fresh install, before migrations).
            return [];
        }
    }
}
