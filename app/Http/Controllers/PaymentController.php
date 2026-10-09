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
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(IndexPaymentsRequest $request): Response|JsonResponse
    {
        $method = $request->string('payment_method')->toString() ?: $request->string('method')->toString();
        $from = $request->string('date_from')->toString() ?: $request->string('from')->toString();
        $to = $request->string('date_to')->toString() ?: $request->string('to')->toString();
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'method' => $method,
            'from' => $from,
            'to' => $to,
            'contract_id' => $request->integer('contract_id') ?: null,
            'client_id' => $request->integer('client_id') ?: null,
        ];
        $base = Payment::query()->where('company_id', $request->user()->company_id);
        $filtered = (clone $base)->with('contract.client')
            ->when($filters['search'] !== '', fn (Builder $q) => $q->whereHas('contract', fn (Builder $q) => $q->where(function (Builder $q) use ($filters): void {
                $q
                    ->where('id', ltrim($filters['search'], '#'))
                    ->orWhereHas('client', fn (Builder $q) => $q
                        ->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('phone', 'like', '%'.$filters['search'].'%')
                        ->orWhere('document', 'like', '%'.$filters['search'].'%'));
            })))
            ->when($filters['method'] !== '', fn (Builder $q) => $q->where('method', $filters['method']))
            ->when($filters['contract_id'] !== null, fn (Builder $q) => $q->where('contract_id', $filters['contract_id']))
            ->when($filters['client_id'] !== null, fn (Builder $q) => $q->whereHas('contract', fn (Builder $q) => $q->where('client_id', $filters['client_id'])))
            ->when($filters['from'] !== '', fn (Builder $q) => $q->where('paid_at', '>=', CarbonImmutable::parse($filters['from'], ContractCalculationService::TIMEZONE)->startOfDay()->utc()))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->where('paid_at', '<=', CarbonImmutable::parse($filters['to'], ContractCalculationService::TIMEZONE)->endOfDay()->utc()));
        $today = CarbonImmutable::today(ContractCalculationService::TIMEZONE);
        $summary = [
            'today' => $this->total((clone $base)->whereBetween('paid_at', [$today->utc(), $today->endOfDay()->utc()])),
            'month' => $this->total((clone $base)->whereBetween('paid_at', [$today->startOfMonth()->utc(), $today->endOfMonth()->utc()])),
            'count' => (clone $filtered)->count(), 'filtered_total' => $this->total(clone $filtered),
            'filtered_discount' => $this->discountTotal(clone $filtered),
        ];
        $perPage = $request->integer('per_page', 15);
        $payments = $filtered->orderByDesc('paid_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
        if ($request->is('api/*')) {
            return PaymentResource::collection($payments)->additional(['summary' => $summary])->response();
        }

        return Inertia::render('payments/Index', ['payments' => $payments->through(fn (Payment $payment): array => (new PaymentResource($payment))->toArray($request)), 'summary' => $summary, 'filters' => $filters]);
    }

    public function show(Request $request, Payment $payment): PaymentResource
    {
        abort_unless($request->user()?->company_id !== null, 403);

        return new PaymentResource(
            Payment::query()
                ->with('contract.client')
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($payment->id)
        );
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
