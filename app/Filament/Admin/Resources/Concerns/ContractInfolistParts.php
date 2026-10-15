<?php

namespace App\Filament\Admin\Resources\Concerns;

use App\Filament\Admin\Resources\Clients\ClientResource;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Models\Installment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** Sections shared by the rent and sale contract views: unit, parties, payment progress, record. */
class ContractInfolistParts
{
    public static function unit(): TextEntry
    {
        return TextEntry::make('unit.title')->label(__('الوحدة'))->weight('bold')->color('primary')
            ->url(fn (Model $record) => $record->unit ? UnitResource::getUrl('view', ['record' => $record->unit]) : null)
            ->helperText(fn (Model $record) => $record->unit?->building?->title);
    }

    public static function parties(string $title): Section
    {
        return Section::make($title)->icon(Heroicon::OutlinedUsers)->schema([
            RepeatableEntry::make('clients')->hiddenLabel()->columns(['default' => 2, 'md' => 4])->schema([
                TextEntry::make('full_name')->label(__('الاسم'))->weight('bold')->columnSpan(2)
                    ->url(fn (Model $record) => ClientResource::getUrl('view', ['record' => $record])),
                TextEntry::make('id_number')->label(__('رقم الهوية'))->placeholder('-'),
                TextEntry::make('mobile')->label(__('الجوال'))->placeholder('-')
                    ->url(fn (Model $record) => filled($record->mobile) ? 'tel:'.$record->mobile : null),
            ])->placeholder(__('لا يوجد أطراف')),
            TextEntry::make('unit.owners.full_name')->label(__('الملاك'))->badge()->color('gray')->placeholder('-'),
        ]);
    }

    public static function progress(): Section
    {
        $sum = fn (Model $record, bool $paid) => $record->installments
            ->sum(fn (Installment $i) => $paid ? $i->paidTotal() : (float) $i->amount);

        return Section::make(__('التحصيل'))->icon(Heroicon::OutlinedChartPie)->columns(2)->schema([
            TextEntry::make('collected_count')->label(__('الدفعات المحصلة'))
                ->state(fn (Model $record) => __(':done من :total', ['done' => $record->installments->where('is_collected', true)->count(), 'total' => $record->installments->count()]))
                ->weight('bold'),
            TextEntry::make('collected_percent')->label(__('نسبة التحصيل'))
                ->state(fn (Model $record) => ($total = $sum($record, false)) > 0 ? round($sum($record, true) / $total * 100).'%' : '-')
                ->badge()->color('success'),
            TextEntry::make('collected_total')->label(__('المحصل'))->numeric(2)
                ->state(fn (Model $record) => $sum($record, true)),
            TextEntry::make('outstanding_total')->label(__('المتبقي'))->numeric(2)
                ->state(fn (Model $record) => $record->installments->sum(fn (Installment $i) => $i->remainingAmount()))
                ->color(fn ($state) => $state > 0 ? 'warning' : null),
            TextEntry::make('overdue_total')->label(__('متأخرات'))->numeric(2)->color('danger')->columnSpanFull()
                ->state(fn (Model $record) => $record->installments
                    ->filter(fn (Installment $i) => ! $i->is_collected && $i->due_date?->isBefore(today()))
                    ->sum(fn (Installment $i) => $i->remainingAmount()))
                ->visible(fn ($state) => $state > 0),
        ]);
    }

    /** @param  list<\Filament\Schemas\Components\Component>  $extra */
    public static function record(array $extra = []): Section
    {
        return Section::make(__('السجل'))->icon(Heroicon::OutlinedClock)->columns(2)->collapsed()->schema([
            TextEntry::make('creator.name')->label(__('أنشئ بواسطة'))->placeholder('-'),
            TextEntry::make('employee.full_name')->label(__('الموظف'))->placeholder('-'),
            TextEntry::make('calendar')->label(__('التقويم'))->badge(),
            TextEntry::make('created_at')->label(__('تاريخ الإضافة'))->dateTime('Y-m-d H:i'),
            TextEntry::make('updated_at')->label(__('آخر تعديل'))->dateTime('Y-m-d H:i'),
            ...$extra,
            TextEntry::make('archived_at')->label(__('تاريخ الأرشفة'))->dateTime('Y-m-d')
                ->visible(fn (Model $record) => $record->archived_at !== null),
            TextEntry::make('deleted_at')->label(__('تاريخ الحذف'))->dateTime('Y-m-d H:i')
                ->visible(fn (Model $record) => method_exists($record, 'trashed') && $record->trashed()),
        ]);
    }
}
