<?php

namespace App\Filament\Admin\Resources\PrintTemplates\Schemas;

use App\Filament\Infolists\Parts;
use App\Models\PrintTemplate;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Print design: header / body / footer as the office sees them, plus the saved versions. */
class PrintTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedPrinter, fn (PrintTemplate $r) => [
                'kicker' => __('قالب طباعة'),
                'title' => $r->title,
                'sub' => [$r->key, $r->legacy_syntax ? __('صيغة النظام القديم') : __('الصيغة الحديثة')],
                'facts' => [
                    Parts::fact('النسخ المحفوظة', $r->versions()->count()),
                    Parts::fact('آخر تعديل', $r->updated_at?->format('Y-m-d')),
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('معاينة'))->icon(Heroicon::OutlinedEye)->schema([
                    TextEntry::make('header')->label(__('الترويسة'))->html()->prose()->placeholder('-'),
                    TextEntry::make('content')->label(__('المحتوى'))->html()->prose(),
                    TextEntry::make('footer')->label(__('التذييل'))->html()->prose()->placeholder('-'),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('القالب'))->icon(Heroicon::OutlinedDocumentText)->columns(2)->schema([
                    TextEntry::make('key')->label(__('المفتاح'))->fontFamily('mono')->copyable(),
                    IconEntry::make('legacy_syntax')->label(__('صيغة قديمة'))->boolean(),
                ]),
                Section::make(__('النسخ السابقة'))->icon(Heroicon::OutlinedClock)->collapsed()
                    ->visible(fn (PrintTemplate $r) => $r->versions()->exists())
                    ->schema([
                        RepeatableEntry::make('recent_versions')->hiddenLabel()->columns(2)
                            ->state(fn (PrintTemplate $r) => $r->versions()->with('creator')->latest()->limit(10)->get())
                            ->schema([
                                TextEntry::make('created_at')->label(__('التاريخ'))->dateTime('Y-m-d H:i'),
                                TextEntry::make('creator.name')->label(__('بواسطة'))->placeholder('-'),
                            ]),
                    ]),
                Parts::record(false),
            ]),
        ]);
    }
}
