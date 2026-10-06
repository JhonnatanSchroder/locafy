<?php

use App\Http\Controllers\ChargeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FinalizeContractController;
use App\Http\Controllers\FreightController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('pagamentos', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('contracts/{contract}/finalize', FinalizeContractController::class)->name('contracts.finalize');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('charges/history', [ChargeController::class, 'history'])->name('charges.history');
    Route::get('charges/history/{charge}', [ChargeController::class, 'historicalShow'])->name('charges.history.show');
    Route::post('contracts/{contract}/payments', [ContractPaymentController::class, 'store'])->name('contracts.payments.store');
    Route::resource('charges', ChargeController::class)->only(['index', 'show', 'store']);
    Route::post('charges/{contract}/payments', [ContractPaymentController::class, 'store'])->name('charges.payments.store');
    Route::post('charges/history/{charge}/payments', [ChargeController::class, 'payment'])->name('charges.history.payments.store');
    Route::patch('charges/{charge}/cancel', [ChargeController::class, 'cancel'])->name('charges.cancel');
    Route::resource('clients', ClientController::class)->except(['destroy']);
    Route::resource('products', ProductController::class)->except(['destroy']);
    Route::resource('equipments', EquipmentController::class)->except(['destroy']);
    Route::resource('contracts', ContractController::class)->except(['destroy']);
    Route::resource('movements', MovementController::class)->except(['destroy']);
    Route::post(
        '/contracts/{contract}/freights',
        [FreightController::class, 'store']
    )->name('contracts.freights.store');
    Route::patch(
        '/contracts/{contract}/freights/{freight}',
        [FreightController::class, 'update']
    )->name('contracts.freights.update');
});

require __DIR__.'/settings.php';
