{{-- Beside the avatar in the topbar: who is signed in and as what; a click opens the user menu. --}}
@php($user = filament()->auth()->user())
@if ($user)
    <button type="button" class="rowad-top-user" x-data x-on:click="$el.parentElement.querySelector('.fi-user-menu-trigger')?.click()">
        <span class="rowad-top-user__text"><b>{{ $user->name }}</b><small>{{ \App\Filament\Support\UserRole::label($user) }}</small></span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
    </button>
@endif
