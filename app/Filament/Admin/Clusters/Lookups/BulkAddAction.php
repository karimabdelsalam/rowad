<?php

namespace App\Filament\Admin\Clusters\Lookups;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy "إضافة متعددة" on every lookup table: paste one name per line and get them all at once,
 * skipping names that already exist (within the same parent, e.g. districts of one city).
 */
final class BulkAddAction
{
    /**
     * @param  class-string<Model>  $model
     * @param  string  $field  the name column ('title' or 'name')
     * @param  list<\Filament\Forms\Components\Field>  $extra  fields every created row shares (a parent select, a default code…)
     * @param  array<string, mixed>  $defaults  attributes added to every row
     */
    public static function make(string $model, string $field = 'title', array $extra = [], array $defaults = []): Action
    {
        return Action::make('bulkAdd')
            ->label(__('إضافة متعددة'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->modalHeading(__('إضافة متعددة'))
            ->modalDescription(__('اكتب اسمًا في كل سطر. الأسماء الموجودة مسبقًا تُتجاهل.'))
            ->schema([
                ...$extra,
                Textarea::make('names')->label(__('الأسماء'))->rows(10)->required()->placeholder("الرياض\nجدة\nالدمام"),
            ])
            ->action(function (array $data) use ($model, $field, $extra, $defaults) {
                $shared = $defaults;
                foreach ($extra as $component) {
                    $shared[$component->getName()] = $data[$component->getName()] ?? null;
                }
                $names = collect(preg_split('/\r\n|\r|\n/', (string) $data['names']))->map(fn ($n) => trim($n))->filter()->unique()->values();
                $existing = $model::query()->where($shared)->whereIn($field, $names)->pluck($field)->map(fn ($n) => mb_strtolower($n))->all();
                $created = 0;
                foreach ($names as $name) {
                    if (in_array(mb_strtolower($name), $existing, true)) {
                        continue;
                    }
                    $model::create([$field => $name] + $shared);
                    $created++;
                }
                Notification::make()->success()
                    ->title(trans_choice('{0} لم يُضف شيء — كلها موجودة|{1} أُضيف عنصر واحد|{2} أُضيف عنصران|[3,*] أُضيف :count عناصر', $created))
                    ->body($names->count() - $created > 0 ? trans_choice('{1} تجاهلنا اسمًا موجودًا|[2,*] تجاهلنا :count أسماء موجودة', $names->count() - $created) : null)
                    ->send();
            });
    }
}
