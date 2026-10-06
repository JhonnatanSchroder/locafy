<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\ContractOperationController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\ContractPaymentController;
use App\Http\Controllers\FinalizeContractController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('contracts/{contract}/finalize', FinalizeContractController::class)->name('contracts.finalize');
        Route::get('me', MeController::class);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::apiResource('clients', ClientController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('products', ProductController::class)->only(['index']);
        Route::apiResource('equipments', EquipmentController::class)->only(['index', 'show']);
        Route::apiResource('contracts', ContractController::class)->only(['index', 'show', 'store', 'update']);
        Route::post('contracts/{contract}/movements', [ContractOperationController::class, 'movement']);
        Route::post('contracts/{contract}/freights', [ContractOperationController::class, 'freight']);
        Route::patch('contracts/{contract}/freights/{freight}', [ContractOperationController::class, 'updateFreight']);
        Route::get('charges/history', [ChargeController::class, 'history'])->name('charges.history');
        Route::get('charges/history/{charge}', [ChargeController::class, 'historicalShow'])->name('charges.history.show');
        Route::post('contracts/{contract}/payments', [ContractPaymentController::class, 'store'])->name('contracts.payments.store');
        Route::apiResource('charges', ChargeController::class)->only(['index', 'show', 'store']);
        Route::post('charges/{contract}/payments', [ContractPaymentController::class, 'store']);
        Route::post('charges/history/{charge}/payments', [ChargeController::class, 'payment']);
    });
});
