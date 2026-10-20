<?php

use App\Http\Controllers\Api\TraceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public read-only API (prefix /api, rate limited by the "api" limiter)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('trace/{token}', [TraceController::class, 'show'])->name('trace.show');

    // Health check endpoint for Docker and monitoring
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'timestamp' => now()->toIso8601String(),
        ]);
    })->name('health');

    // Metrics endpoint for Prometheus monitoring
    Route::get('metrics', function () {
        $metrics = [];

        // Application metrics
        $metrics[] = '# HELP nutritrace_app_info Application information';
        $metrics[] = '# TYPE nutritrace_app_info gauge';
        $metrics[] = 'nutritrace_app_info{version="1.0.0",environment="'.config('app.env').'"} 1';

        // Database connection check
        try {
            DB::connection()->getPdo();
            $dbStatus = 1;
        } catch (Exception $e) {
            $dbStatus = 0;
        }
        $metrics[] = '# HELP nutritrace_database_up Database connection status';
        $metrics[] = '# TYPE nutritrace_database_up gauge';
        $metrics[] = 'nutritrace_database_up '.$dbStatus;

        // Cache connection check
        try {
            Cache::has('test');
            $cacheStatus = 1;
        } catch (Exception $e) {
            $cacheStatus = 0;
        }
        $metrics[] = '# HELP nutritrace_cache_up Cache connection status';
        $metrics[] = '# TYPE nutritrace_cache_up gauge';
        $metrics[] = 'nutritrace_cache_up '.$cacheStatus;

        // AI service status
        $aiEnabled = config('ai.enabled') ? 1 : 0;
        $metrics[] = '# HELP nutritrace_ai_enabled AI service enabled status';
        $metrics[] = '# TYPE nutritrace_ai_enabled gauge';
        $metrics[] = 'nutritrace_ai_enabled '.$aiEnabled;

        // Request count (simple counter from database if using database sessions)
        try {
            $userCount = DB::table('users')->count();
            $metrics[] = '# HELP nutritrace_users_total Total number of users';
            $metrics[] = '# TYPE nutritrace_users_total gauge';
            $metrics[] = 'nutritrace_users_total '.$userCount;

            $organizationCount = DB::table('organizations')->count();
            $metrics[] = '# HELP nutritrace_organizations_total Total number of organizations';
            $metrics[] = '# TYPE nutritrace_organizations_total gauge';
            $metrics[] = 'nutritrace_organizations_total '.$organizationCount;

            $productCount = DB::table('products')->count();
            $metrics[] = '# HELP nutritrace_products_total Total number of products';
            $metrics[] = '# TYPE nutritrace_products_total gauge';
            $metrics[] = 'nutritrace_products_total '.$productCount;
        } catch (Exception $e) {
            // Database not available yet
        }

        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $metrics[] = '# HELP nutritrace_memory_bytes Memory usage in bytes';
        $metrics[] = '# TYPE nutritrace_memory_bytes gauge';
        $metrics[] = 'nutritrace_memory_bytes '.$memoryUsage;

        return response(implode("\n", $metrics)."\n")
            ->header('Content-Type', 'text/plain; version=0.0.4');
    })->name('metrics');
});
