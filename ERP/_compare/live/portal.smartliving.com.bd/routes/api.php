<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/warranty-serials', [\App\Http\Controllers\WarrantyApiController::class, 'getSerials']);

Route::get('/sales-lookup', [\App\Http\Controllers\SalesLookupApiController::class, 'lookup']);

Route::get('/app-lookup/serial', [\App\Http\Controllers\AppLookupController::class, 'bySerial']);

Route::get('/app-lookup/phone',  [\App\Http\Controllers\AppLookupController::class, 'byPhone']);