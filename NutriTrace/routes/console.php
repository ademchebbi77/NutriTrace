<?php

use Illuminate\Support\Facades\Schedule;

// Requires the scheduler: `php artisan schedule:work` in development, a cron entry in production.
Schedule::command('certifications:expire')->dailyAt('01:00');
