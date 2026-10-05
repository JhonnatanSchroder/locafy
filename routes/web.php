<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FreightController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
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
