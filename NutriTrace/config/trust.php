<?php

/*
|--------------------------------------------------------------------------
| Transparency ("anti-greenwashing") score settings
|--------------------------------------------------------------------------
|
| Weights add up to 100. Penalties are subtracted afterwards. An administrator can
| override these values from the back office; overrides live in the "settings" table.
|
*/

return [

    // Maximum points of each component.
    'weights' => [
        'chain_completeness' => 25,
        'verified_actors' => 20,
        'certifications' => 20,
        'environmental_data' => 20,
        'chain_integrity' => 15,
    ],

    // Points removed per occurrence, and the cap on the total removed.
    'penalties' => [
        'expired_certification' => 5,
        'rejected_certification' => 10,
        'open_report' => 5,
        'chain_gap' => 5,
        'max_total' => 40,
    ],

    // A lot is "Local" when its farm(s) and its final destination are within this distance.
    'local_radius_km' => 150,

    // A "local" claim on a lot that travelled more than this raises a greenwashing warning.
    'local_claim_max_food_miles_km' => 250,

];
