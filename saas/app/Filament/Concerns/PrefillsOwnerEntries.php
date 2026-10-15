<?php

namespace App\Filament\Concerns;

use App\Models\OwnerTransaction;

/** Voucher forms opened from the owners' ledger (?owner=ID&entries=1,2) — legacy statementsin/out add?transid=. */
trait PrefillsOwnerEntries
{
    protected function afterFill(): void
    {
        $owner = (int) request()->query('owner');
        $ids = array_filter(array_map('intval', explode(',', (string) request()->query('entries'))));
        if ($owner <= 0 || $ids === []) {
            return;
        }
        $entries = OwnerTransaction::whereKey($ids)->where('owner_id', $owner)->get();

        $this->form->fill(array_merge($this->form->getRawState(), [
            'party_type' => 'owner',
            'party_id' => $owner,
            'owner_transaction_ids' => $entries->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'unit_id' => $entries->pluck('unit_id')->filter()->unique()->count() === 1 ? $entries->first()->unit_id : null,
            'amount' => round((float) $entries->sum('amount'), 2) ?: null,
        ]));
    }
}
