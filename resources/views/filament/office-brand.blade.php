@php
    use App\Support\Branding;
    $logo = Branding::logoUrl();
    $name = Branding::name();
    $panel = filament()->getCurrentPanel()?->getId();
@endphp
<span class="rowad-brand {{ $logo ? 'rowad-brand--logo' : '' }}" title="{{ $name }}">
    @if ($logo)
        {{-- an uploaded logo normally carries the name itself, so it stands alone --}}
        <img class="rowad-brand__logo" src="{{ $logo }}" alt="{{ $name }}">
        <span class="fi-sr-only">{{ $name }}</span>
    @else
        <span class="rowad-brand__mark" aria-hidden="true">{{ Branding::initials() }}</span>
        <span class="rowad-brand__text">
            <span class="rowad-brand__name">{{ $name }}</span>
            <span class="rowad-brand__panel">{{ Branding::panelTitle($panel) }}</span>
        </span>
    @endif
</span>
