<?php

use App\Enums\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;

if (! function_exists('area_route')) {
    /**
     * URL of a route inside the logged-in user's own area, e.g.
     * area_route('lots.index') gives /producteur/lots for a producer.
     * Lets modules shared by several roles reuse the same controllers and views.
     */
    function area_route(string $name, mixed $parameters = []): string
    {
        return route(auth()->user()->role->prefix().'.'.$name, $parameters);
    }
}

if (! function_exists('area_has_route')) {
    function area_has_route(string $name): bool
    {
        return auth()->check() && Route::has(auth()->user()->role->prefix().'.'.$name);
    }
}

if (! function_exists('event_moment')) {
    /**
     * Moment to stamp on a traceability event for a record that only has a date:
     * a date that is today becomes the current time, so the event sorts after
     * what already happened earlier today.
     */
    function event_moment(?CarbonInterface $date): CarbonInterface
    {
        return $date === null || $date->isToday() ? now() : $date;
    }
}

if (! function_exists('format_quantity')) {
    /**
     * French number formatting with the unit symbol, e.g. "5 000 kg" or "12,5 L".
     */
    function format_quantity(float $quantity, Unit $unit): string
    {
        $decimals = fmod($quantity, 1.0) === 0.0 ? 0 : 2;

        return number_format($quantity, $decimals, ',', ' ').' '.$unit->symbol();
    }
}
