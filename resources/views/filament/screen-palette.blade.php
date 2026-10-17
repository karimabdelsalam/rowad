{{--
    Ctrl+K / ⌘K: jump to any screen of the panel by name. The list is the panel's own menu (only what this
    user may open), grouped as in the sidebar; arrows to move, Enter to open, Esc to close.
    Record search (clients, contracts, units…) is the search row under the top bar.
--}}
@php
    $screens = [];
    foreach (filament()->getNavigation() as $group) {
        $groupLabel = (string) ($group->getLabel() ?? '');
        foreach ($group->getItems() as $item) {
            foreach ([$item, ...$item->getChildItems()] as $entry) {
                if (! $entry->isVisible() || blank($entry->getUrl())) {
                    continue;
                }
                $icon = $entry->getIcon() ?? $group->getIcon();
                $screens[] = [
                    'label' => (string) $entry->getLabel(),
                    'group' => $groupLabel,
                    'url' => $entry->getUrl(),
                    'icon' => $icon ? \Filament\Support\generate_icon_html($icon)?->toHtml() : null,
                    'active' => $entry->isActive(),
                ];
            }
        }
    }
    $mac = str_contains((string) request()->userAgent(), 'Mac');
@endphp
<div class="rowad-screens" x-data="rowadScreens(@js($screens))"
    x-on:keydown.window.ctrl.k.prevent.stop="toggle()" x-on:keydown.window.meta.k.prevent.stop="toggle()" x-on:rowad-screens-open.window="show()">
    <button type="button" class="rowad-screens__trigger" x-on:click="show()" aria-haspopup="dialog">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>
        <span class="rowad-screens__trigger-text">{{ __('انتقل إلى شاشة…') }}</span>
        <kbd dir="ltr">{{ $mac ? '⌘K' : 'Ctrl K' }}</kbd>
    </button>

    <template x-teleport="body">
        <div class="rowad-screens__overlay" x-show="open" x-cloak x-transition.opacity.duration.120ms x-on:click.self="close()" x-on:keydown.escape.window="close()">
            <div class="rowad-screens__dialog" role="dialog" aria-modal="true" aria-label="{{ __('الانتقال إلى شاشة') }}" x-show="open" x-transition.scale.98.duration.120ms>
                <div class="rowad-screens__head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    <input x-ref="input" type="text" x-model="q" x-on:input="cursor = 0" placeholder="{{ __('اكتب اسم الشاشة أو القسم…') }}" autocomplete="off" spellcheck="false"
                        x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.enter.prevent="go(results[cursor])">
                    <kbd x-on:click="close()" role="button">Esc</kbd>
                </div>
                <div class="rowad-screens__list" x-ref="list">
                    <template x-for="(screen, i) in results" :key="screen.url">
                        <div>
                            <p class="rowad-screens__group" x-show="i === 0 || results[i - 1].group !== screen.group" x-text="screen.group"></p>
                            <a class="rowad-screens__item" :href="screen.url" :class="{ 'is-active': i === cursor, 'is-current': screen.active }" :data-index="i"
                                x-on:mouseenter="cursor = i" x-on:click.prevent="go(screen)">
                                <span class="rowad-screens__icon" x-html="screen.icon || ''"></span>
                                <span class="rowad-screens__label" x-text="screen.label"></span>
                                <span class="rowad-screens__meta" x-text="screen.group"></span>
                            </a>
                        </div>
                    </template>
                    <p class="rowad-screens__empty" x-show="results.length === 0">{{ __('لا توجد شاشة بهذا الاسم.') }}</p>
                </div>
                <div class="rowad-screens__foot">
                    <span><kbd>↑</kbd><kbd>↓</kbd> {{ __('للتنقل') }}</span>
                    <span><kbd>Enter</kbd> {{ __('للفتح') }}</span>
                    <span><kbd>Esc</kbd> {{ __('للإغلاق') }}</span>
                    <span class="rowad-screens__tip"><kbd>/</kbd> {{ __('للبحث في السجلات') }}</span>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    <script>
        // "/" anywhere outside a text field jumps to the record search row
        document.addEventListener('keydown', (event) => {
            if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
            const target = event.target;
            if (target.closest?.('input, textarea, select, [contenteditable="true"], .rowad-screens__overlay')) return;
            const field = document.querySelector('.fi-topbar .fi-global-search-field input');
            if (! field) return;
            event.preventDefault();
            field.focus();
        });

        document.addEventListener('alpine:init', () => window.Alpine.data('rowadScreens', (screens) => ({
            screens,
            open: false,
            q: '',
            cursor: 0,
            norm(text) {
                return (text || '').toLowerCase().replace(/[ً-ْـ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').trim();
            },
            get results() {
                const words = this.norm(this.q).split(/\s+/).filter(Boolean);
                if (words.length === 0) return this.screens;
                return this.screens.filter((s) => {
                    const hay = this.norm(s.label + ' ' + s.group);
                    return words.every((w) => hay.includes(w));
                });
            },
            show() {
                this.open = true;
                this.q = '';
                this.cursor = Math.max(0, this.screens.findIndex((s) => s.active));
                this.$nextTick(() => { this.$refs.input?.focus(); this.reveal(); });
            },
            close() { this.open = false; },
            toggle() { this.open ? this.close() : this.show(); },
            move(step) {
                const n = this.results.length;
                if (n === 0) return;
                this.cursor = (this.cursor + step + n) % n;
                this.$nextTick(() => this.reveal());
            },
            reveal() {
                this.$refs.list?.querySelector(`[data-index="${this.cursor}"]`)?.scrollIntoView({ block: 'nearest' });
            },
            go(screen) {
                if (! screen) return;
                this.close();
                window.location.href = screen.url;
            },
        })));
    </script>
@endonce
