<?php

use App\Http\Middleware\VerifyCloudScheduler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyCloudScheduler::class)
    ->post('/cloud-scheduler/run', function () {
        Artisan::call('schedule:run');

        return response()->json([
            'output' => Artisan::output(),
        ]);
    });
