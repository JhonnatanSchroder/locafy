<?php

namespace App\Http\Controllers;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditController extends Controller
{
    public function index(Request $request): Response|AnonymousResourceCollection|JsonResponse
    {
        $this->authorizeAdmin($request);
        $query = $this->filteredQuery($request);
        $audits = $query->paginate(20)->withQueryString();
        $users = User::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(['id', 'name']);

        if ($request->is('api/*')) {
            return response()->json(['data' => $audits->getCollection()->map(fn (AdminAudit $audit): array => $this->summary($audit))->values(), 'meta' => ['current_page' => $audits->currentPage(), 'last_page' => $audits->lastPage(), 'per_page' => $audits->perPage(), 'total' => $audits->total()]]);
        }

        return Inertia::render('admin/audits/Index', [
            'audits' => $audits->through(fn (AdminAudit $audit): array => $this->summary($audit)),
            'filters' => $request->only(['from', 'to', 'module', 'operation', 'user_id']),
            'users' => $users,
        ]);
    }

    public function show(Request $request, int $audit): Response|JsonResponse
    {
        $this->authorizeAdmin($request);
        $record = $this->filteredQuery($request)->with('user:id,name,role')->findOrFail($audit);
        $data = $this->detail($record);

        return $request->is('api/*') ? response()->json(['data' => $data]) : Inertia::render('admin/audits/Show', ['audit' => $data]);
    }

    /** @return Builder<AdminAudit> */
    private function filteredQuery(Request $request): Builder
    {
        return AdminAudit::query()
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('module'), fn (Builder $query) => $query->where('module', $request->string('module')->toString()))
            ->when($request->filled('operation'), fn (Builder $query) => $query->where('operation', $request->string('operation')->toString()))
            ->when($request->filled('entity_type'), fn (Builder $query) => $query->where('entity_type', $request->string('entity_type')->toString()))
            ->when($request->filled('entity_id'), fn (Builder $query) => $query->where('entity_id', $request->integer('entity_id')))
            ->when($request->filled('user_id'), fn (Builder $query) => $query->where('user_id', $request->integer('user_id')))
            ->with('user:id,name,role')
            ->latest('created_at')
            ->latest('id');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->canAdministrateCompany(), 403);
    }

    /** @return array<string, mixed> */
    private function summary(AdminAudit $audit): array
    {
        return ['id' => $audit->id, 'module' => $audit->module, 'entity_type' => $audit->entity_type, 'entity_id' => $audit->entity_id, 'operation' => $audit->operation, 'source' => $audit->source, 'user' => $audit->user?->name ?? $audit->user_snapshot['name'] ?? null, 'created_at' => $audit->created_at?->toIso8601String()];
    }

    /** @return array<string, mixed> */
    private function detail(AdminAudit $audit): array
    {
        return [...$this->summary($audit), 'company_id' => $audit->company_id, 'user_id' => $audit->user_id, 'user_snapshot' => $audit->user_snapshot, 'reason' => $audit->reason, 'before_state' => $audit->before_state, 'after_state' => $audit->after_state];
    }
}
