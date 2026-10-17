<?php

namespace App\Filament\Support;

use App\Http\Controllers\PrintController;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** "Print" and "PDF" for any printable document, on its page or its table row; shown only to who may print it. */
final class DocumentActions
{
    /** @return list<Action> */
    public static function make(string $type, ?string $printLabel = null): array
    {
        return [self::print($type, $printLabel), self::pdf($type)];
    }

    public static function print(string $type, ?string $label = null): Action
    {
        return Action::make('print')->label($label ?? __('طباعة'))->icon(Heroicon::OutlinedPrinter)->color('gray')
            ->url(fn (Model $record) => self::url($type, $record), shouldOpenInNewTab: true)
            ->visible(fn () => self::allowed($type));
    }

    /** @param  bool  $portal  owners / tenants: the print page itself checks the document is theirs */
    public static function pdf(string $type, bool $portal = false): Action
    {
        return Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
            ->tooltip(__('تحميل نسخة PDF'))
            ->url(fn (Model $record) => self::url($type, $record, pdf: true), shouldOpenInNewTab: true)
            ->visible(fn () => $portal || self::allowed($type));
    }

    public static function url(string $type, Model $record, bool $pdf = false): string
    {
        return route('documents.print', array_filter(['type' => $type, 'id' => $record->getKey(), 'pdf' => $pdf ? 1 : null]));
    }

    private static function allowed(string $type): bool
    {
        $user = auth()->user();
        $permission = PrintController::TYPES[$type][1] ?? null;

        return $user !== null && ($user->is_super_admin || $permission === null || $user->can($permission));
    }
}
