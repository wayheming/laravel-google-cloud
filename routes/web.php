<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health/check', function () {
    $checks = [];

    // PostgreSQL
    try {
        DB::connection()->getPdo();
        $checks['database'] = '✅ Connected (' . DB::connection()->getDatabaseName() . ')';
    } catch (\Throwable $e) {
        $checks['database'] = '❌ ' . $e->getMessage();
    }

    // Redis
    try {
        Redis::ping();
        $checks['redis'] = '✅ Connected';
    } catch (\Throwable $e) {
        $checks['redis'] = '❌ ' . $e->getMessage();
    }

    // Cache (via configured store)
    try {
        Cache::put('health_check', 'ok', 10);
        $value = Cache::get('health_check');
        $checks['cache'] = $value === 'ok' ? '✅ Working (' . config('cache.default') . ')' : '❌ Failed';
    } catch (\Throwable $e) {
        $checks['cache'] = '❌ ' . $e->getMessage();
    }

    // Cloud Tasks queue config
    try {
        $connection = config('queue.default');
        $checks['queue'] = '✅ Driver: ' . $connection;
        if ($connection === 'cloudtasks') {
            $checks['queue'] .= ' (project: ' . config('queue.connections.cloudtasks.project') . ')';
        }
    } catch (\Throwable $e) {
        $checks['queue'] = '❌ ' . $e->getMessage();
    }

    return response()->json($checks);
});
