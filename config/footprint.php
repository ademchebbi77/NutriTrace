<?php

/*
|--------------------------------------------------------------------------
| Environmental footprint settings
|--------------------------------------------------------------------------
|
| SIMPLIFIED VALUES for a teaching project: orders of magnitude inspired by public
| databases (ADEME Base Carbone, GLEC), not certified figures. An administrator can
| override them from the back office ("Paramètres de calcul"); the overrides are
| stored in the "settings" table and merged over this file at boot.
|
*/

return [

    'emission_factors' => [
        // kg CO2e per tonne-kilometre, by transport mode.
        'transport' => [
            'truck' => 0.105,
            'van' => 0.250,
            'train' => 0.028,
            'ship' => 0.016,
            'plane' => 0.602,
        ],
        // kg CO2e per kWh of electricity (Tunisian grid, rounded).
        'electricity' => 0.47,
        // kg CO2e per kg of product applied.
        'fertilizer' => 5.0,
        'pesticide' => 10.0,
    ],

    /*
    | Score scale of each indicator, per kilogram of product (per litre for liquids):
    | "best" or lower scores 100, "worst" or higher scores 0, linear in between.
    */
    'thresholds' => [
        'co2_per_kg' => ['best' => 0.1, 'worst' => 1.5],
        'water_per_kg' => ['best' => 10, 'worst' => 150],
        'energy_per_kg' => ['best' => 0.05, 'worst' => 1.5],
        'food_miles' => ['best' => 30, 'worst' => 400],
    ],

    // Weight of each indicator in the overall score. Missing indicators are left out
    // and the remaining weights are rescaled.
    'weights' => [
        'co2' => 0.4,
        'water' => 0.2,
        'energy' => 0.2,
        'food_miles' => 0.2,
    ],

    // Minimum score for each grade; anything below D is E.
    'grades' => [
        'A' => 80,
        'B' => 60,
        'C' => 40,
        'D' => 20,
    ],

];
