@php
    use App\Filament\Admin\Resources as R;
    $user = filament()->auth()->user();
    if (filament()->getCurrentPanel()?->getId() !== 'admin' || ! $user) {
        return;
    }
    $items = collect([
        ['units.create', R\Units\UnitResource::class, 'وحدة', 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21'],
        ['rent_contracts.create', R\RentContracts\RentContractResource::class, 'عقد إيجار', 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
        ['receipt_vouchers.create', R\ReceiptVouchers\ReceiptVoucherResource::class, 'سند قبض', 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z'],
        ['clients.create', R\Clients\ClientResource::class, 'عميل', 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
        ['owners.create', R\Owners\OwnerResource::class, 'مالك', 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
    ])->filter(fn ($i) => ($user->is_super_admin || $user->can($i[0])) && $i[1]::canCreate());
    if ($items->isEmpty()) {
        return;
    }
@endphp
<div class="rowad-quick" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    <button type="button" class="rowad-quick__btn" x-on:click="open = ! open" :aria-expanded="open" aria-haspopup="menu" title="{{ __('إضافة سريعة') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        <span>{{ __('جديد') }}</span>
    </button>
    <div class="rowad-quick__menu" role="menu" x-show="open" x-cloak x-transition.origin.top>
        @foreach ($items as [$permission, $resource, $label, $path])
            <a role="menuitem" href="{{ $resource::getUrl('create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                <span>{{ __($label) }}</span>
            </a>
        @endforeach
    </div>
</div>
