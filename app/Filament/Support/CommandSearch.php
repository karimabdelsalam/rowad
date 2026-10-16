<?php

namespace App\Filament\Support;

use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Filament\Navigation\NavigationItem;

/**
 * The top search box as a command palette (Ctrl+K / ⌘K): besides records (units, people, contracts, vouchers…)
 * it finds the pages of the panel and "new …" actions by name, so any screen is two keystrokes away.
 */
class CommandSearch extends DefaultGlobalSearchProvider
{
    public const LIMIT = 6;

    public function getResults(string $query): ?GlobalSearchResults
    {
        $needle = self::normalize($query);
        $records = parent::getResults($query);
        $results = GlobalSearchResults::make();

        $shortcuts = $needle === '' ? [] : [...$this->actions($needle), ...$this->pages($needle)];
        if ($shortcuts !== []) {
            $results->category(__('الصفحات والإجراءات'), array_slice($shortcuts, 0, self::LIMIT));
        }
        foreach ($records?->getCategories() ?? [] as $name => $items) {
            $results->category($name, $items);
        }

        return $results;
    }

    /** "New rent contract", "New unit"… for every resource the user may create. @return list<GlobalSearchResult> */
    private function actions(string $needle): array
    {
        // "اضافة وحدة" / "new unit": the verb (or its start) is dropped and the rest names the thing; the verb alone lists everything
        $verbs = array_map(self::normalize(...), [__('إضافة'), __('جديد'), 'إضافة', 'جديد', 'add', 'new', 'create']);
        $isVerb = fn (string $word) => mb_strlen($word) >= 2 && collect($verbs)->contains(fn (string $verb) => str_starts_with($verb, $word));
        $thing = implode(' ', array_filter(explode(' ', $needle), fn (string $word) => $word !== '' && ! $isVerb($word)));

        $out = [];
        foreach (Filament::getResources() as $resource) {
            if (! $resource::hasPage('create') || ! $resource::canCreate()) {
                continue;
            }
            $label = (string) $resource::getModelLabel();
            if ($thing === '' || self::matches($thing, $label, (string) $resource::getPluralModelLabel())) {
                $out[] = new GlobalSearchResult(__('إضافة :label', ['label' => $label]), $resource::getUrl('create'), [__('إجراء') => __('إنشاء جديد')]);
            }
        }

        return $out;
    }

    /** Pages in the navigation whose name (or group) matches. @return list<GlobalSearchResult> */
    private function pages(string $needle): array
    {
        $out = [];
        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                /** @var NavigationItem $item */
                if (! $item->isVisible() || blank($url = $item->getUrl())) {
                    continue;
                }
                if (self::matches($needle, (string) $item->getLabel(), (string) $group->getLabel())) {
                    $out[] = new GlobalSearchResult((string) $item->getLabel(), $url, array_filter([__('القسم') => $group->getLabel()]));
                }
            }
        }

        return $out;
    }

    private static function matches(string $needle, string ...$haystacks): bool
    {
        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains(self::normalize($haystack), $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Arabic-friendly matching: alef forms, taa marbuta / haa, alef maqsura / yaa, no tashkeel, lower case. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text);

        return strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
    }
}
