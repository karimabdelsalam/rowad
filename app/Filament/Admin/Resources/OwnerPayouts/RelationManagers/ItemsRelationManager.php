<?php

namespace App\Filament\Admin\Resources\OwnerPayouts\RelationManagers;

use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Filament\Admin\Resources\PaymentVouchers\PaymentVoucherResource;
use App\Models\OwnerPayoutItem;
use App\Support\Iban;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** One line per owner: what was due, what was deducted, what is transferred and where. */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('الملاك في الدفعة');
    }

    public function table(Table $table): Table
    {
        $money = fn (string $name, string $label) => TextColumn::make($name)->label(__($label))->numeric(2)->summarize(Sum::make()->numeric(2)->label(''));

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['owner', 'paymentVoucher']))
            ->defaultSort('amount', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('owner.full_name')->label(__('المالك'))->weight('bold')->searchable()
                    ->url(fn (OwnerPayoutItem $r) => $r->owner ? OwnerResource::getUrl('view', ['record' => $r->owner]) : null)
                    ->description(fn (OwnerPayoutItem $r) => trans_choice('{1} حركة واحدة|{2} حركتان|[3,10] :count حركات|[11,*] :count حركة', $r->entries_count)),
                TextColumn::make('iban')->label(__('الآيبان'))->fontFamily('mono')->size('xs')->copyable()
                    ->formatStateUsing(fn (?string $state) => Iban::format($state))->extraAttributes(['dir' => 'ltr'])
                    ->description(fn (OwnerPayoutItem $r) => $r->bank_name.($r->beneficiary !== $r->owner?->full_name ? ' · '.$r->beneficiary : '')),
                $money('dues', 'المستحقات'),
                $money('deductions', 'الخصومات')->color('danger'),
                $money('amount', 'صافي التحويل')->weight('bold')->color('success'),
                TextColumn::make('paymentVoucher.number')->label(__('سند الصرف'))->placeholder('-')
                    ->url(fn (OwnerPayoutItem $r) => $r->paymentVoucher ? PaymentVoucherResource::getUrl('view', ['record' => $r->paymentVoucher]) : null),
            ]);
    }
}
