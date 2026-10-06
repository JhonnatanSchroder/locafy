<?php

use App\Actions\Charges\EnsureDueChargesExist;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('charges:ensure-due', function () {
    $count = app(EnsureDueChargesExist::class)->handle();
    $this->info('Materialization disabled; receivables use live contract balances.');
})->purpose('Legacy compatibility command; contract receivables are live');
// Contract receivables need no materialization or scheduled database writes.
