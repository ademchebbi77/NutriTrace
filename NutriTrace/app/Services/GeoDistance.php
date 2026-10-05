<?php

namespace App\Services;

class GeoDistance
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Great-circle distance between two points, in kilometres (Haversine formula).
     */
    public function km(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $deltaLatitude = deg2rad($latitudeB - $latitudeA);
        $deltaLongitude = deg2rad($longitudeB - $longitudeA);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($deltaLongitude / 2) ** 2;

        return round(2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a))), 2);
    }
}
