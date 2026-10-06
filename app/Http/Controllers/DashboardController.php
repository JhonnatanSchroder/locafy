<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Payment;
use App\Services\ContractCalculationService;
use App\Services\ContractLifecycleService;
use App\Services\ContractReceivablesService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ContractReceivablesService $receivables): Response
    {
        $company = $request->user()->company_id;
        if ($company !== null) {
            app(ContractLifecycleService::class)->repairCompany($company);
        }
        $returned = Contract::query()->where('company_id', $company)->where('status', 'RETURNED')->get()->map(fn ($contract) => $receivables->data($contract));
        $today = CarbonImmutable::today(ContractCalculationService::TIMEZONE);
        $outstanding = $company === null ? collect() : $receivables->rows($company, 'outstanding');
        $overdue = $outstanding->filter(fn ($row) => $row['days_overdue'] > 0);
        $contracts = Contract::query()->where('company_id', $company);
        $payments = Payment::query()->where('company_id', $company)->whereBetween('paid_at', [$today->startOfMonth()->utc(), CarbonImmutable::now()])->get();

        return Inertia::render('Dashboard', [
            'today' => $today->toDateString(),
            'metrics' => [
                'active' => (clone $contracts)->where('status', 'ACTIVE')->count(),
                'returned_pending' => $returned->where('display_status', 'PAYMENT_PENDING')->count(),
                'ready_to_finalize' => $returned->where('can_finalize', true)->count(),
                'returned' => (clone $contracts)->where('status', 'RETURNED')->count(),
                'today' => $outstanding->where('due_today', true)->count(), 'overdue' => $overdue->count(),
                'balance' => Money::format($outstanding->sum(fn ($row) => Money::cents($row['balance']))),
                'received' => Money::format($payments->sum(fn ($payment) => Money::cents($payment->amount))),
            ],
            'attention' => $overdue->take(5)->values(),
            'recentContracts' => (clone $contracts)->with('client')->latest('id')->limit(6)->get()->map(fn ($contract) => [
                ...$receivables->data($contract), 'started_at' => $contract->started_at->toIso8601String(),
            ])->all(),
        ]);
    }
}
