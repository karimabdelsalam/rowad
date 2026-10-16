@php($money = fn ($v) => number_format((float) $v, 2))
<x-filament-panels::page>
    <div class="rowad-import">
        <section class="rowad-einv__card">
            {{ $this->form }}
        </section>

        <section class="rowad-einv__card rowad-import__preview">
            <div class="rowad-payout__bar">
                <div class="rowad-import__stats">
                    <div><b>{{ number_format($rows->count()) }}</b><span>{{ __('مالك له مستحقات') }}</span></div>
                    <div class="is-ok"><b>{{ number_format($chosenCount) }}</b><span>{{ __('محدد للتحويل') }}</span></div>
                    <div class="is-ok"><b>{{ $money($chosenTotal) }}</b><span>{{ __('إجمالي التحويل') }}</span></div>
                </div>
                <x-filament::button wire:click="create" icon="heroicon-o-banknotes" :disabled="$chosenCount === 0" wire:target="create">
                    {{ __('إنشاء الدفعة وسندات الصرف') }}
                </x-filament::button>
            </div>

            @if ($rows->isEmpty())
                <p class="rowad-import__note">{{ __('لا توجد مستحقات مفتوحة للملاك حتى هذا التاريخ.') }}</p>
            @else
                <table class="rowad-einv__table rowad-payout__table">
                    <thead>
                        <tr>
                            <th style="width: 36px"><input type="checkbox" class="rowad-payout__check" @checked($chosenCount > 0 && $chosenCount === $rows->where('ready', true)->count())
                                wire:click="toggleAll($event.target.checked)" aria-label="{{ __('تحديد الكل') }}"></th>
                            <th>{{ __('المالك') }}</th>
                            <th>{{ __('الحساب البنكي') }}</th>
                            <th class="is-num">{{ __('المستحقات') }}</th>
                            <th class="is-num">{{ __('الخصومات') }}</th>
                            <th class="is-num">{{ __('صافي التحويل') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="owner-{{ $row['owner']->id }}" @class(['is-off' => ! $row['ready']])>
                                <td><input type="checkbox" class="rowad-payout__check" value="{{ $row['owner']->id }}" wire:model.live="selected" @disabled(! $row['ready'])></td>
                                <td>
                                    <strong>{{ $row['owner']->full_name }}</strong>
                                    <small>{{ trans_choice('{1} حركة واحدة|{2} حركتان|[3,10] :count حركات|[11,*] :count حركة', $row['entries']) }}</small>
                                </td>
                                <td>
                                    @if ($row['ready'])
                                        <span dir="ltr" class="rowad-payout__iban">{{ \App\Support\Iban::format($row['iban']) }}</span>
                                        <small>{{ $row['bank'] }}</small>
                                    @else
                                        <span class="rowad-einv__badge is-warn">{{ blank($row['iban']) ? __('لا يوجد آيبان') : __('آيبان غير صحيح') }}</span>
                                        <a class="rowad-einv__link" href="{{ $ownerUrl($row['owner']) }}">{{ __('إضافة الآيبان') }} ←</a>
                                    @endif
                                </td>
                                <td class="is-num">{{ $money($row['dues']) }}</td>
                                <td class="is-num is-bad">@if ($row['deductions'] > 0)<bdi dir="ltr">−{{ $money($row['deductions']) }}</bdi>@else - @endif</td>
                                <td class="is-num"><strong>{{ $money($row['net']) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
</x-filament-panels::page>
