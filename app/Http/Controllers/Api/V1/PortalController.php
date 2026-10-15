<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketType;
use App\Enums\UserType;
use App\Http\Resources\V1\ContractResource;
use App\Http\Resources\V1\InstallmentResource;
use App\Http\Resources\V1\LedgerEntryResource;
use App\Http\Resources\V1\TicketResource;
use App\Http\Resources\V1\UnitResource;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\SupportTicket;
use App\Services\Reports\DashboardStats;
use App\Support\Portal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Owner and client app endpoints — the same data as the /owner and /client portals, scoped to the signed-in party. */
class PortalController extends Controller
{
    public function ownerSummary(DashboardStats $stats): JsonResponse
    {
        return response()->json(['data' => $stats->owner(Portal::owner())]);
    }

    public function ownerUnits(Request $request): AnonymousResourceCollection
    {
        return UnitResource::collection(Portal::ownerUnits(Portal::owner())->with(['building', 'owners'])->orderBy('title')->paginate($this->perPage($request)));
    }

    /** Owner statement (legacy units/transactions): debit = owed to the owner, credit = deducted. */
    public function ownerLedger(Request $request): AnonymousResourceCollection
    {
        return LedgerEntryResource::collection(OwnerTransaction::query()->with(['type', 'unit'])
            ->where('owner_id', Portal::owner()->getKey())
            ->when($request->has('settled'), fn (Builder $query) => $query->where('is_settled', $request->boolean('settled')))
            ->orderByDesc('due_date')->orderByDesc('id')->paginate($this->perPage($request)));
    }

    public function clientSummary(DashboardStats $stats): JsonResponse
    {
        $summary = $stats->client(Portal::client());
        $summary['next'] = $summary['next'] ? new InstallmentResource($summary['next']) : null;

        return response()->json(['data' => $summary]);
    }

    public function clientContracts(Request $request): JsonResponse
    {
        $client = Portal::client();
        $mine = fn (Builder $query) => $query->whereKey($client->getKey());
        $contracts = RentContract::with(['unit', 'clients'])->whereHas('clients', $mine)->latest('id')->get()
            ->concat(SaleContract::with(['unit', 'clients'])->whereHas('clients', $mine)->latest('id')->get());

        return response()->json(['data' => ContractResource::collection($contracts)]);
    }

    public function clientInstallments(Request $request): AnonymousResourceCollection
    {
        $client = Portal::client();

        return InstallmentResource::collection(Installment::query()->with('unit')
            ->whereHasMorph('contract', [RentContract::class, SaleContract::class],
                fn (Builder $query) => $query->whereHas('clients', fn (Builder $query) => $query->whereKey($client->getKey())))
            ->when($request->query('status') === 'collected', fn (Builder $query) => $query->where('is_collected', true), fn (Builder $query) => $query->where('is_collected', false))
            ->orderBy('due_date')->paginate($this->perPage($request)));
    }

    public function tickets(Request $request): AnonymousResourceCollection
    {
        return TicketResource::collection(SupportTicket::query()->with('replies')
            ->where($this->ticketOwner($request))->latest('id')->paginate($this->perPage($request)));
    }

    public function createTicket(Request $request): TicketResource
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(TicketType::class)],
            'unit_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);
        $owner = $this->ticketOwner($request);
        $units = $request->user()->type === UserType::Owner ? Portal::ownerUnits(Portal::owner()) : Portal::clientUnits(Portal::client());
        if (filled($data['unit_id'] ?? null) && ! $units->whereKey($data['unit_id'])->exists()) {
            $data['unit_id'] = null; // never trust the app for ownership
        }

        $ticket = SupportTicket::create($owner + $data + SupportTicket::newTicketDefaults());

        return new TicketResource($ticket->load('replies'));
    }

    /** @return array{owner_id: int}|array{client_id: int} */
    private function ticketOwner(Request $request): array
    {
        return $request->user()->type === UserType::Owner
            ? ['owner_id' => Portal::owner()->getKey()]
            : ['client_id' => Portal::client()->getKey()];
    }
}
