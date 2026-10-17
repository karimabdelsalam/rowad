<?php

namespace App\Filament\Admin\Resources\OwnerPayouts\Pages;

use App\Enums\OwnerPayoutStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Resources\OwnerPayouts\OwnerPayoutResource;
use App\Models\OwnerPayout;
use App\Services\Payouts\OwnerPayouts;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOwnerPayout extends ViewRecord
{
    protected static string $resource = OwnerPayoutResource::class;

    public function getTitle(): string
    {
        return __('دفعة توزيع :n', ['n' => $this->getRecord()->number]);
    }

    public static function allowed(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('owner_payouts.'.$ability));
    }

    protected function getHeaderActions(): array
    {
        /** @var OwnerPayout $payout */
        $payout = $this->getRecord();
        $prepared = $payout->status === OwnerPayoutStatus::Prepared;

        return [
            ...\App\Filament\Support\DocumentActions::make('owner_payout'),
            Action::make('bankFile')->label(__('ملف التحويل البنكي'))->icon(Heroicon::OutlinedArrowDownTray)
                ->visible($payout->status !== OwnerPayoutStatus::Cancelled)
                ->action(fn () => response()->download(app(OwnerPayouts::class)->bankFile($payout), $payout->number.'.xlsx')->deleteFileAfterSend()),
            Action::make('transferred')->label(__('تم التحويل من البنك'))->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible($prepared && self::allowed('transfer'))
                ->requiresConfirmation()
                ->modalDescription(__('أكّد بعد تنفيذ البنك للتحويلات. يُرسل لكل مالك إشعار بالمبلغ المحوّل ولا يمكن بعدها إلغاء الدفعة.'))
                ->action(fn () => $this->run(fn () => app(OwnerPayouts::class)->markTransferred($payout), __('تم إشعار الملاك بالتحويل'))),
            ActionGroup::make([
                Action::make('csv')->label(__('الملف بصيغة CSV'))->icon(Heroicon::OutlinedDocumentText)
                    ->visible($payout->status !== OwnerPayoutStatus::Cancelled)
                    ->action(fn () => response()->download(app(OwnerPayouts::class)->bankFile($payout, 'csv'), $payout->number.'.csv', ['Content-Type' => 'text/csv'])->deleteFileAfterSend()),
                Action::make('cancel')->label(__('إلغاء الدفعة'))->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible($prepared && self::allowed('cancel'))
                    ->requiresConfirmation()
                    ->modalDescription(__('تُحذف سندات الصرف وتعود مستحقات الملاك مفتوحة كما كانت.'))
                    ->action(fn () => $this->run(fn () => app(OwnerPayouts::class)->cancel($payout), __('أُلغيت الدفعة'))),
            ]),
        ];
    }

    private function run(callable $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->success()->title($success)->send();
            $this->redirect(OwnerPayoutResource::getUrl('view', ['record' => $this->getRecord()]));
        } catch (BusinessRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
