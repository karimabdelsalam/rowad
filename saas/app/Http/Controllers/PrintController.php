<?php

namespace App\Http\Controllers;

use App\Enums\UserType;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\User;
use App\Services\Documents\DocumentData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Legacy "printable" actions: HTML documents ready for the browser's print / save-as-PDF. */
class PrintController
{
    private const TYPES = [
        'rent_contract' => [RentContract::class, 'rent_contracts.print'],
        'sale_contract' => [SaleContract::class, 'sale_contracts.print'],
        'receipt_voucher' => [ReceiptVoucher::class, 'receipt_vouchers.print'],
        'payment_voucher' => [PaymentVoucher::class, 'payment_vouchers.print'],
        'invoice' => [Invoice::class, 'invoices.print'],
        'owner_statement' => [Owner::class, 'owner_transactions.view_any'],
        'letter' => [\App\Models\Letter::class, 'letters.print'],
    ];

    public function __invoke(Request $request, string $type, int $id, DocumentData $documents): View
    {
        [$class, $permission] = self::TYPES[$type] ?? abort(404);
        $record = $class::findOrFail($id);
        abort_unless($this->allowed($request->user(), $record, $permission), 403);

        $html = match (true) {
            $record instanceof RentContract => $documents->rentContract($record),
            $record instanceof SaleContract => $documents->saleContract($record),
            $record instanceof ReceiptVoucher, $record instanceof PaymentVoucher => $documents->voucher($record),
            $record instanceof Invoice => $documents->invoice($record),
            $record instanceof Owner => $documents->ownerStatement($record, $request->query('from'), $request->query('to')),
            $record instanceof \App\Models\Letter => $documents->letter($record),
        };

        $title = match (true) {
            $record instanceof Owner => $record->full_name,
            $record instanceof \App\Models\Letter => $record->type?->getLabel(),
            default => $record->number,
        };

        return view('print.document', ['html' => $html, 'title' => $title ?: __('مستند')]);
    }

    /** Public check behind the QR printed on contracts. */
    public function verify(string $type, int $id): View
    {
        $class = ['rent_contract' => RentContract::class, 'sale_contract' => SaleContract::class][$type] ?? abort(404);
        $contract = $class::with(['unit', 'clients'])->findOrFail($id);

        return view('print.verify', ['contract' => $contract]);
    }

    private function allowed(?User $user, Model $record, string $permission): bool
    {
        if ($user === null || ! $user->is_active) {
            return false;
        }
        if ($user->type === UserType::Staff) {
            return $user->is_super_admin || $user->can($permission);
        }

        $owner = $user->owner;
        $client = $user->client;

        return match (true) {
            $record instanceof RentContract, $record instanceof SaleContract =>
                ($owner && $record->unit->owners()->whereKey($owner->id)->exists())
                || ($client && $record->clients()->whereKey($client->id)->exists()),
            $record instanceof ReceiptVoucher, $record instanceof PaymentVoucher =>
                ($owner && $record->party_type === 'owner' && (int) $record->party_id === $owner->id)
                || ($client && $record->party_type === 'client' && (int) $record->party_id === $client->id),
            $record instanceof Invoice => $client && (int) $record->client_id === $client->id,
            $record instanceof Owner => $owner && $owner->is($record),
            default => false,
        };
    }
}
