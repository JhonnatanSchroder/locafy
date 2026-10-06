<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\DeleteContractAttachmentAction;
use App\Actions\Contracts\StoreContractAttachmentsAction;
use App\Enums\ContractStatus;
use App\Http\Requests\Contracts\StoreContractAttachmentsRequest;
use App\Http\Resources\ContractAttachmentResource;
use App\Models\Contract;
use App\Models\ContractAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractAttachmentController extends Controller
{
    public function index(Request $request, Contract $contract): AnonymousResourceCollection
    {
        $contract = $this->contractForUser($request, $contract);

        return ContractAttachmentResource::collection($contract->attachments()->with('uploader')->latest()->get());
    }

    public function store(StoreContractAttachmentsRequest $request, Contract $contract, StoreContractAttachmentsAction $action): AnonymousResourceCollection|RedirectResponse
    {
        $attachments = $action->handle($contract, $request->file('attachments', []), $request->user());

        if ($request->is('api/*')) {
            $attachments->each->load('uploader');

            return ContractAttachmentResource::collection($attachments);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fotos adicionadas ao contrato.']);

        return back();
    }

    public function show(Request $request, Contract $contract, ContractAttachment $attachment): StreamedResponse
    {
        $this->attachmentForUser($request, $contract, $attachment);

        return Storage::disk($this->disk())->response($attachment->file_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroy(Request $request, Contract $contract, ContractAttachment $attachment, DeleteContractAttachmentAction $action): RedirectResponse|JsonResponse
    {
        $attachment = $this->attachmentForUser($request, $contract, $attachment);
        abort_if(in_array($contract->status, [ContractStatus::Finalized, ContractStatus::Cancelled], true), 403);

        $action->handle($attachment);

        if ($request->is('api/*')) {
            return response()->json(status: 204);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto removida.']);

        return back();
    }

    private function contractForUser(Request $request, Contract $contract): Contract
    {
        abort_unless($contract->company_id === $request->user()?->company_id, 404);

        return $contract;
    }

    private function attachmentForUser(Request $request, Contract $contract, ContractAttachment $attachment): ContractAttachment
    {
        $this->contractForUser($request, $contract);
        abort_unless($attachment->contract_id === $contract->id && $attachment->company_id === $contract->company_id, 404);

        return $attachment;
    }

    private function disk(): string
    {
        return (string) config('filesystems.contract_attachments_disk', config('filesystems.default'));
    }
}
