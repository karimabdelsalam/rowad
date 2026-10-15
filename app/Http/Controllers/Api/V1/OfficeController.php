<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Resources\V1\ContractResource;
use App\Http\Resources\V1\InstallmentResource;
use App\Http\Resources\V1\PartyResource;
use App\Http\Resources\V1\UnitResource;
use App\Http\Resources\V1\VoucherResource;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Owner;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;
use App\Services\Installments\InstallmentCollector;
use App\Services\Reports\DashboardStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Office staff endpoints; each one checks the same permission as its admin screen. */
class OfficeController extends Controller
{
    public function dashboard(Request $request, DashboardStats $stats): JsonResponse
    {
        $this->authorizePermission($request, 'units.view_any');

        return response()->json(['data' => $stats->office() + ['collections' => $stats->monthlyCollections((int) now()->year)]]);
    }

    public function units(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, 'units.view_any');

        return UnitResource::collection(Unit::query()->with('building')
            ->when($request->query('status'), fn (Builder $query, $status) => $query->where('status', $status))
            ->when($request->query('building_id'), fn (Builder $query, $id) => $query->where('building_id', $id))
            ->when($request->query('search'), fn (Builder $query, $search) => $query->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%")))
            ->orderByDesc('id')->paginate($this->perPage($request)));
    }

    public function unit(Request $request, Unit $unit): UnitResource
    {
        $this->authorizePermission($request, 'units.view');

        return new UnitResource($unit->load(['building', 'owners']));
    }

    public function contracts(Request $request, string $type): AnonymousResourceCollection
    {
        $model = $this->contractModel($request, $type, 'view_any');

        return ContractResource::collection($model::query()->with(['unit', 'clients'])
            ->when($request->query('status'), fn (Builder $query, $status) => $query->where('status', $status))
            ->when($request->query('unit_id'), fn (Builder $query, $id) => $query->where('unit_id', $id))
            ->orderByDesc('id')->paginate($this->perPage($request)));
    }

    public function contract(Request $request, string $type, int $id): ContractResource
    {
        $model = $this->contractModel($request, $type, 'view');

        return new ContractResource($model::with(['unit', 'clients', 'installments' => fn ($query) => $query->orderBy('due_date')])->findOrFail($id));
    }

    public function installments(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, 'installments.view_any');

        $query = Installment::query()->with('unit')
            ->when($request->query('unit_id'), fn (Builder $query, $id) => $query->where('unit_id', $id))
            ->when($request->query('contract_id'), fn (Builder $query, $id) => $query
                ->where('contract_type', $request->query('contract_type') === 'sale' ? 'sale_contract' : 'rent_contract')->where('contract_id', $id));

        match ($request->query('status')) {
            'collected' => $query->where('is_collected', true)->orderByDesc('collected_at'),
            'overdue' => $query->where('is_collected', false)->whereDate('due_date', '<', today())->orderBy('due_date'),
            default => $query->where('is_collected', false)->orderBy('due_date'), // due
        };

        return InstallmentResource::collection($query->paginate($this->perPage($request)));
    }

    public function collect(Request $request, Installment $installment, InstallmentCollector $collector): InstallmentResource|JsonResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'close' => ['boolean'],
            'settle_owners' => ['boolean'],
        ]);
        $partial = isset($data['amount']) && round((float) $data['amount'], 2) < round((float) $installment->amount - (float) $installment->paid_amount, 2);
        $this->authorizePermission($request, $partial ? 'installments.partial_collect' : 'installments.collect');

        try {
            $installment = $partial
                ? $collector->collectPartial($installment, (float) $data['amount'], null, (bool) ($data['close'] ?? false), (bool) ($data['settle_owners'] ?? false))
                : $collector->collect($installment);
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new InstallmentResource($installment->refresh()->load('unit'));
    }

    public function parties(Request $request, string $type): AnonymousResourceCollection
    {
        $model = $type === 'owners' ? Owner::class : Client::class;
        $this->authorizePermission($request, "{$type}.view_any");

        return PartyResource::collection($model::query()
            ->when($request->query('search'), fn (Builder $query, $search) => $query->where(fn (Builder $query) => $query
                ->where('full_name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%")->orWhere('id_number', 'like', "%{$search}%")))
            ->orderBy('full_name')->paginate($this->perPage($request)));
    }

    public function party(Request $request, string $type, int $id): PartyResource
    {
        $this->authorizePermission($request, "{$type}.view");

        return new PartyResource(($type === 'owners' ? Owner::class : Client::class)::findOrFail($id));
    }

    public function vouchers(Request $request, string $kind): AnonymousResourceCollection
    {
        $model = $kind === 'receipt' ? ReceiptVoucher::class : PaymentVoucher::class;
        $this->authorizePermission($request, ($kind === 'receipt' ? 'receipt_vouchers' : 'payment_vouchers').'.view_any');

        return VoucherResource::collection($model::query()->with(['party', 'unit'])
            ->when($request->query('from'), fn (Builder $query, $from) => $query->whereDate('issued_at', '>=', $from))
            ->when($request->query('to'), fn (Builder $query, $to) => $query->whereDate('issued_at', '<=', $to))
            ->orderByDesc('issued_at')->orderByDesc('id')->paginate($this->perPage($request)));
    }

    /** @return class-string<RentContract|SaleContract> */
    private function contractModel(Request $request, string $type, string $ability): string
    {
        $this->authorizePermission($request, ($type === 'rent' ? 'rent_contracts' : 'sale_contracts').".{$ability}");

        return $type === 'rent' ? RentContract::class : SaleContract::class;
    }
}
