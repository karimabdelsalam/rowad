<?php

namespace App\Filament\Admin\Resources\RentContracts;

use App\Exceptions\BusinessRuleException;
use App\Models\ContractSignature;
use App\Models\RentContract;
use App\Services\Signing\ContractSigning;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/** Asking the parties to sign a rent contract electronically, and where each signature stands. */
final class SignatureActions
{
    public static function request(): Action
    {
        return Action::make('requestSignatures')
            ->label(fn (RentContract $record) => $record->signatures()->exists() ? __('إعادة إرسال روابط التوقيع') : __('طلب التوقيع الإلكتروني'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->visible(fn (RentContract $record) => $record->signed_at === null && self::can())
            ->requiresConfirmation()
            ->modalDescription(__('يصل كل مالك ومستأجر رابط خاص على جواله لمراجعة العقد وتوقيعه بعد تأكيد هويته برمز تحقق. من وقّع لا يُطلب منه ثانية ما لم تتغير بنود العقد.'))
            ->action(function (RentContract $record) {
                try {
                    $sent = app(ContractSigning::class)->request($record);
                    Notification::make()->success()->title(trans_choice('{0} وقّع جميع الأطراف|{1} أُرسل رابط التوقيع لطرف واحد|[2,*] أُرسلت روابط التوقيع إلى :count أطراف', count($sent)))->send();
                } catch (BusinessRuleException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    public static function section(): Section
    {
        return Section::make(__('التوقيع الإلكتروني'))->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (RentContract $record) => $record->signatures()->exists())
            ->schema([
                TextEntry::make('signed_at')->label(__('اكتمل التوقيع'))->dateTime('Y-m-d H:i')->color('success')->weight('bold')
                    ->visible(fn (RentContract $record) => $record->signed_at !== null),
                RepeatableEntry::make('signatures')->hiddenLabel()->contained(false)->schema([
                    TextEntry::make('name')->hiddenLabel()->weight('bold')
                        ->state(fn (ContractSignature $record) => $record->name.' · '.$record->roleLabel()),
                    TextEntry::make('status')->hiddenLabel()->badge()
                        ->state(fn (ContractSignature $record) => $record->statusLabel().($record->signed_at ? ' · '.$record->signed_at->format('Y-m-d H:i') : ''))
                        ->color(fn (ContractSignature $record) => match ($record->status) {
                            ContractSignature::SIGNED => 'success', ContractSignature::DECLINED => 'danger', default => 'warning',
                        })
                        ->helperText(fn (ContractSignature $record) => $record->decline_reason),
                ])->columns(1),
            ]);
    }

    private static function can(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('rent_contracts.update'));
    }
}
