<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPaymentsRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\ContractCalculationService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(IndexPaymentsRequest $request): Response|JsonResponse
    {
        $filters = ['search' => $request->string('search')->trim()->toString(), 'method' => $request->string('method')->toString(), 'from' => $request->string('from')->toString(), 'to' => $request->string('to')->toString()];
        $base = Payment::query()->where('company_id', $request->user()->company_id);
        $filtered = (clone $base)->with('contract.client')
            ->when($filters['search'] !== '', fn (Builder $q) => $q->whereHas('contract', fn (Builder $q) => $q->where(function (Builder $q) use ($filters): void {
                $q->where('id', ltrim($filters['search'], '#'))->orWhereHas('client', fn (Builder $q) => $q->where('name', 'like', '%'.$filters['search'].'%'));
            })))
            ->when($filters['method'] !== '', fn (Builder $q) => $q->where('method', $filters['method']))
            ->when($filters['from'] !== '', fn (Builder $q) => $q->where('paid_at', '>=', CarbonImmutable::parse($filters['from'], ContractCalculationService::TIMEZONE)->startOfDay()->utc()))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->where('paid_at', '<=', CarbonImmutable::parse($filters['to'], ContractCalculationService::TIMEZONE)->endOfDay()->utc()));
        $today = CarbonImmutable::today(ContractCalculationService::TIMEZONE);
        $summary = [
            'today' => $this->total((clone $base)->whereBetween('paid_at', [$today->utc(), $today->endOfDay()->utc()])),
            'month' => $this->total((clone $base)->whereBetween('paid_at', [$today->startOfMonth()->utc(), $today->endOfMonth()->utc()])),
            'count' => (clone $filtered)->count(), 'filtered_total' => $this->total(clone $filtered),
            'filtered_discount' => $this->discountTotal(clone $filtered),
        ];
        $payments = $filtered->orderByDesc('paid_at')->orderByDesc('id')->paginate(15)->withQueryString();
        if ($request->is('api/*')) {
            return PaymentResource::collection($payments)->additional(['summary' => $summary])->response();
        }

        return Inertia::render('payments/Index', ['payments' => $payments->through(fn (Payment $payment): array => (new PaymentResource($payment))->toArray($request)), 'summary' => $summary, 'filters' => $filters]);
    }

    /** @param Builder<Payment> $query */
    private function total(Builder $query): string
    {
        return Money::format($query->get(['amount'])->sum(fn (Payment $payment): int => Money::cents($payment->amount)));
    }

    /** @param Builder<Payment> $query */
    private function discountTotal(Builder $query): string
    {
        return Money::format($query->get(['discount_amount'])->sum(fn (Payment $payment): int => Money::cents((string) ($payment->discount_amount ?? '0.00'))));
    }
}
