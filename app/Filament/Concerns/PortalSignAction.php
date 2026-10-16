<?php

namespace App\Filament\Concerns;

use App\Models\ContractSignature;
use App\Models\RentContract;
use App\Services\Signing\ContractSigning;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** "Sign the contract" in the tenant/owner portal, for a party whose signature the office requested. */
final class PortalSignAction
{
    public static function make(\Closure $person): Action
    {
        $pending = fn (RentContract $record) => ContractSignature::query()->whereMorphedTo('signable', $record)
            ->whereMorphedTo('signer', $person())->where('status', ContractSignature::PENDING)->latest('id')->first();

        return Action::make('sign')
            ->label(__('وقّع العقد'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->button()
            ->visible(fn (RentContract $record) => $pending($record) !== null)
            ->action(fn (RentContract $record) => redirect()->away(app(ContractSigning::class)->linkFor($pending($record))));
    }

    /** @return \Closure(): Model */
    public static function owner(): \Closure
    {
        return fn () => \App\Support\Portal::owner();
    }

    /** @return \Closure(): Model */
    public static function client(): \Closure
    {
        return fn () => \App\Support\Portal::client();
    }
}
