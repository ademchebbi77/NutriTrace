<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo data for Product and Production modules only.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,           // admin, demo actors (producers)
            CatalogSeeder::class,        // categories and products
            ProductionSeeder::class,     // farm productions (Member 1's work)
        ]);
    }
}
