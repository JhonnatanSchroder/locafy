<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', MeController::class);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::apiResource('clients', ClientController::class)->only(['index', 'show']);
        Route::apiResource('products', ProductController::class)->only(['index']);
        Route::apiResource('equipments', EquipmentController::class)->only(['index', 'show']);
        Route::apiResource('contracts', ContractController::class)->only(['index', 'show']);
    });
});
