<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Website\HomeController;
use App\Http\Controllers\WebsiteDataController;

Route::get('/ping', function () {
    return response()->json([
        'status'  => 'success',
        'message' => 'Website API is connected successfully!',
        'data'    => [
            'site_name' => 'My Website',
            'version'   => '1.0.0',
            'time'      => now()->toDateTimeString(),
        ]
    ], 200);
});
Route::get('/summary-stats', [WebsiteDataController::class, 'summaryStats']);
Route::get('/campaigns', [WebsiteDataController::class, 'index']);
Route::get('/top-volunteers', [WebsiteDataController::class, 'topVolunteers']);
Route::get('/campaigns/{id}', [WebsiteDataController::class, 'show']);
