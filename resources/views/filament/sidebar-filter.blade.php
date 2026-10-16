{{-- Filters the menu as you type; Enter opens the first match. --}}
<div class="rowad-nav-filter" x-data="rowadNavFilter" x-show="$store.sidebar.isOpen" x-cloak>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
    <input type="search" x-model="q" x-on:input="filter()" x-on:keydown.enter.prevent="go()" x-on:keydown.escape="q = ''; filter()"
        placeholder="{{ __('ابحث في القائمة…') }}" aria-label="{{ __('ابحث في القائمة…') }}" autocomplete="off">
</div>
