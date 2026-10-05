<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Model events stay enabled on purpose:
     * observers build lots, the traceability chain and the scores from the seeded records.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,           // admin, demo actors and consumers
            CatalogSeeder::class,        // categories and products
            ProductionSeeder::class,     // farm productions -> lots
            JourneySeeder::class,        // transfers, transformations, transports, distributions, sales
            SustainabilitySeeder::class, // certifications and declared environmental data
            CommunitySeeder::class,      // reviews, reports, history, favorites
            VolumeSeeder::class,         // more regions, actors, products and journeys (can also run alone)
        ]);
    }
}
