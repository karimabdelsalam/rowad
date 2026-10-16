<?php

namespace App\Filament\Admin\Resources\Installments\Tables;

use App\Enums\InstallmentType;
use App\Exceptions\BusinessRuleException;
use App\Models\Installment;
use App\Services\Installments\InstallmentCollector;
use App\Support\Hijri;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Legacy pays_pending + pays_processed screens in one table. */
class InstallmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unit', 'contract.clients']))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')
                    ->label(__('الاستحقاق'))
                    ->date('Y-m-d')
                    ->weight('bold')
                    ->color(fn (Installment $record) => ! $record->is_collected && $record->due_date?->isPast() ? 'danger' : null)
                    ->description(fn (Installment $record) => $record->calendar?->value === 'hijri' ? Hijri::toString($record->due_date) : null)
                    ->sortable(),
                TextColumn::make('unit.title')->label(__('الوحدة'))->searchable()->sortable()->weight('medium')
                    ->description(fn (Installment $record) => $record->contract?->clients?->pluck('full_name')->implode('، ')),
                TextColumn::make('contract_label')
                    ->label(__('العقد'))
                    ->state(fn (Installment $record) => $record->contract?->number ?? '#'.$record->contract_id)
                    ->toggleable(),
                TextColumn::make('type')->label(__('النوع'))->badge(),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2)->sortable()->weight('bold'),
                TextColumn::make('paid_amount')->label(__('المدفوع'))->state(fn (Installment $record) => $record->paidTotal())->numeric(2)->color('success')->toggleable(),
                TextColumn::make('remaining')
                    ->label(__('المتبقي'))
                    ->state(fn (Installment $record) => $record->remainingAmount())
                    ->numeric(2)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('vat_amount')->label(__('الضريبة'))->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('buyer_commission')->label(__('عمولة المشتري'))->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seller_commission')->label(__('عمولة البائع'))->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')
                    ->label(__('الحالة'))
                    ->badge()
                    ->state(fn (Installment $record) => $record->is_collected ? 'collected' : ($record->due_date?->isPast() ? 'late' : ((float) $record->paid_amount > 0 ? 'partial' : 'due')))
                    ->formatStateUsing(fn ($state) => ['collected' => __('محصّل'), 'late' => __('متأخر'), 'partial' => __('جزئي'), 'due' => __('مستحق')][$state])
                    ->color(fn ($state) => ['collected' => 'success', 'late' => 'danger', 'partial' => 'warning', 'due' => 'info'][$state])
                    ->icon(fn ($state) => ['collected' => Heroicon::OutlinedCheckCircle, 'late' => Heroicon::OutlinedExclamationCircle, 'partial' => Heroicon::OutlinedClock, 'due' => Heroicon::OutlinedCalendar][$state]),
                TextColumn::make('escalation_stage')->label(__('سلّم التحصيل'))->badge()->toggleable()
                    ->state(fn (Installment $record) => $record->escalation_stage > 0 ? __(\App\Services\Collection\CollectionEscalation::STAGES[$record->escalation_stage]) : null)
                    ->color(fn (Installment $record) => $record->escalation_stage >= 3 ? 'danger' : 'warning')
                    ->url(fn (Installment $record) => $record->warning_letter_id ? route('documents.print', ['type' => 'letter', 'id' => $record->warning_letter_id]) : null, true),
                TextColumn::make('collected_at')->label(__('تاريخ التحصيل'))->dateTime('Y-m-d')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_collected')->default(false),
                SelectFilter::make('type')->options(InstallmentType::class),
                Filter::make('overdue')
                    ->label(__('متأخرة'))
                    ->query(fn (Builder $query) => $query->where('is_collected', false)->whereDate('due_date', '<', today())),
                SelectFilter::make('escalation_stage')->label(__('سلّم التحصيل'))
                    ->options(collect(\App\Services\Collection\CollectionEscalation::STAGES)->map(fn ($l) => __($l))->all()),
                Filter::make('due_this_month')
                    ->label(__('مستحقة هذا الشهر'))
                    ->query(fn (Builder $query) => $query->whereBetween('due_date', [today()->startOfMonth(), today()->endOfMonth()])),
            ])
            ->recordActions([
                Action::make('collect')
                    ->label(__('تحصيل'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('تحصيل القسط'))
                    ->modalDescription(fn (Installment $record) => self::describe($record))
                    ->visible(fn (Installment $record) => ! $record->is_collected && self::allowed('collect'))
                    // the collected row leaves the "not collected" list and the next one takes its place, so say which one it was
                    ->action(fn (Installment $record) => self::run(fn () => app(InstallmentCollector::class)->collect($record),
                        __('تم تحصيل قسط :unit', ['unit' => $record->unit?->title]), self::describe($record).' — '.__('انتقل إلى الأقساط المحصّلة.'))),
                \Filament\Actions\ActionGroup::make([
                ViewAction::make(),
                Action::make('payLink')
                    ->label(__('رابط الدفع'))
                    ->icon(Heroicon::OutlinedLink)
                    ->color('gray')
                    ->visible(fn (Installment $record) => ! $record->is_collected && \App\Services\Payments\Payments::enabled())
                    ->modalHeading(__('رابط الدفع الإلكتروني'))
                    ->modalDescription(__('أرسله للمستأجر بأي وسيلة؛ عند الدفع يُحصَّل القسط ويصدر سند القبض تلقائيًا.'))
                    ->modalContent(function (Installment $record) {
                        $url = app(\App\Services\Payments\Payments::class)->urlFor($record);
                        $client = $record->contract?->clients()->first();
                        $text = __('مرحبًا :name، رابط سداد قسط :unit بمبلغ :amount ر.س: :url', ['name' => $client?->displayName(), 'unit' => $record->unit?->title,
                            'amount' => number_format($record->remainingAmount(), 2), 'url' => $url]);

                        return view('filament.admin.pay-link', ['url' => $url, 'amount' => $record->remainingAmount(),
                            'whatsapp' => filled($client?->mobile) ? \App\Services\Messaging\WhatsAppGateway::shareUrl($client->mobile, $text) : null]);
                    })
                    ->modalSubmitAction(false)->modalCancelActionLabel(__('إغلاق')),
                Action::make('collectPartial')
                    ->label(__('دفعة جزئية'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('warning')
                    ->visible(fn (Installment $record) => ! $record->is_collected && self::allowed('partial_collect'))
                    ->schema(fn (Installment $record) => [
                        TextInput::make('amount')
                            ->label(__('المبلغ'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue($record->remainingAmount())
                            ->helperText(__('المتبقي').': '.number_format($record->remainingAmount(), 2)),
                        Toggle::make('close')->label(__('ترحيل القسط بعد الدفعة')),
                        Toggle::make('settle_owners')->label(__('تسوية حساب الملاك لهذا القسط')),
                    ])
                    ->action(fn (Installment $record, array $data) => self::run(
                        fn () => app(InstallmentCollector::class)->collectPartial($record, (float) $data['amount'], null, (bool) $data['close'], (bool) $data['settle_owners']),
                        __('تم تسجيل الدفعة'),
                    )),
                Action::make('uncollect')
                    ->label(__('إرجاع إلى مستحق'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('سيُلغى تحصيل القسط وتُحذف حركات الملاك الناتجة عنه.'))
                    ->visible(fn (Installment $record) => $record->is_collected && self::allowed('reset'))
                    ->action(fn (Installment $record) => self::run(fn () => app(InstallmentCollector::class)->uncollect($record), __('تم إرجاع القسط إلى مستحق'))),
                Action::make('resetPartial')
                    ->label(__('إلغاء الدفعات الجزئية'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Installment $record) => ! $record->is_collected && (float) $record->paid_amount > 0 && self::allowed('reset'))
                    ->action(fn (Installment $record) => self::run(fn () => app(InstallmentCollector::class)->resetPartial($record), __('تم إلغاء الدفعات الجزئية'))),
                Action::make('warn')
                    ->label(__('إصدار إنذار رسمي'))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('يصدر خطاب إنذار بكل الأقساط المتأخرة في هذا العقد، ويُبلغ المستأجر.'))
                    ->visible(fn (Installment $record) => ! $record->is_collected && $record->warning_letter_id === null && $record->due_date?->isPast()
                        && $record->contract_type === 'rent_contract' && self::allowed('collect'))
                    ->action(function (Installment $record) {
                        $late = Installment::query()->where('contract_type', $record->contract_type)->where('contract_id', $record->contract_id)
                            ->where('is_collected', false)->where('type', '!=', \App\Enums\InstallmentType::Expenses->value)
                            ->whereNull('warning_letter_id')->whereDate('due_date', '<', today())->get();
                        $letter = app(\App\Services\Collection\CollectionEscalation::class)->warn($late);
                        Notification::make()->success()->title(__('صدر الإنذار'))
                            ->actions([Action::make('print')->label(__('طباعة'))->url(route('documents.print', ['type' => 'letter', 'id' => $letter->id]), shouldOpenInNewTab: true)])
                            ->send();
                    }),
                Action::make('printReceipt')
                    ->label(__('طباعة سند القبض'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->visible(fn (Installment $record) => $record->receipt_voucher_id !== null)
                    ->url(fn (Installment $record) => route('documents.print', ['type' => 'receipt_voucher', 'id' => $record->receipt_voucher_id]), shouldOpenInNewTab: true),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('issueInvoice')
                        ->label(__('إصدار فاتورة'))
                        ->icon(Heroicon::OutlinedReceiptPercent)
                        ->requiresConfirmation()
                        ->visible(fn () => self::allowed('invoice'))
                        ->action(function (Collection $records) {
                            try {
                                $invoice = app(\App\Services\Finance\InvoiceService::class)->fromInstallments($records->load(['unit', 'contract.clients']));
                                Notification::make()->title(__('تم إصدار الفاتورة :number', ['number' => $invoice->number]))->success()
                                    ->actions([\Filament\Actions\Action::make('print')->label(__('طباعة'))
                                        ->url(route('documents.print', ['type' => 'invoice', 'id' => $invoice->id]), shouldOpenInNewTab: true)])
                                    ->send();
                            } catch (BusinessRuleException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                            }
                        }),
                    BulkAction::make('collectSelected')
                        ->label(__('تحصيل المحدد'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->requiresConfirmation()
                        ->visible(fn () => self::allowed('collect'))
                        ->action(fn (Collection $records) => self::run(
                            fn () => $records->each(fn (Installment $i) => app(InstallmentCollector::class)->collect($i)),
                            __('تم تحصيل الأقساط المحددة'),
                        )),
                ]),
            ]);
    }

    private static function allowed(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can("installments.{$ability}"));
    }

    /** "Majed Al-Ghamdi · 2,833.33 · due 2026-10-01 · RC-2026-00004" — which installment, at a glance. */
    private static function describe(Installment $record): string
    {
        $ltr = fn (?string $v) => $v === null ? null : "\u{2066}{$v}\u{2069}"; // dates and numbers keep their order inside Arabic text

        return collect([
            $record->contract?->clients?->pluck('full_name')->implode('، '),
            number_format($record->remainingAmount(), 2).' '.\App\Support\Settings::currencyLabel(),
            __('استحقاق :date', ['date' => $ltr($record->due_date?->format('Y-m-d'))]),
            $ltr($record->contract?->number),
        ])->filter()->implode(' · ');
    }

    private static function run(callable $callback, string $success, ?string $body = null): void
    {
        try {
            $callback();
            Notification::make()->title($success)->body($body)->success()->send();
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
