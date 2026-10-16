<?php

namespace App\Filament\Concerns;

use App\Enums\MaintenanceStatus as S;
use App\Filament\Infolists\Parts;
use App\Models\MaintenanceRequest;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

/** What tenants and owners see of a maintenance request in their portals. */
trait PortalMaintenance
{
    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Parts::header(Heroicon::OutlinedWrenchScrewdriver, fn (MaintenanceRequest $r) => [
                'kicker' => $r->code.' · '.$r->category->getLabel(),
                'title' => $r->title,
                'sub' => [$r->unit?->title, __('فُتح :when', ['when' => $r->created_at?->locale(app()->getLocale())->diffForHumans()])],
                'facts' => array_values(array_filter([
                    Parts::fact('الحالة', $r->status->getLabel(), $r->status === S::Completed ? 'success' : ($r->status->isOpen() ? 'warning' : null)),
                    $r->scheduled_at && $r->status->isOpen() ? Parts::fact('موعد الزيارة', $r->scheduled_at->format('Y-m-d H:i')) : null,
                    static::showsCost() && ($r->cost ?? $r->estimated_cost) !== null ? Parts::fact($r->cost !== null ? 'التكلفة' : 'التقديرية', Parts::amount($r->cost ?? $r->estimated_cost)) : null,
                ])),
            ]),
            Section::make(__('التفاصيل'))->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->columns(2)->schema([
                TextEntry::make('description')->label(__('وصف المشكلة'))->placeholder('-')->columnSpanFull(),
                TextEntry::make('technician_name')->label(__('الفني'))->placeholder(__('لم يُحدد بعد')),
                TextEntry::make('technician_phone')->label(__('جوال الفني'))->placeholder('-')->extraAttributes(['dir' => 'ltr'])
                    ->url(fn ($state) => filled($state) ? 'tel:'.$state : null),
                TextEntry::make('resolution')->label(__('ما الذي تم'))->placeholder('-')->columnSpanFull()
                    ->visible(fn (MaintenanceRequest $r) => $r->status === S::Completed),
                TextEntry::make('cancel_reason')->label(__('سبب الإلغاء'))->columnSpanFull()
                    ->visible(fn (MaintenanceRequest $r) => $r->status === S::Cancelled),
                TextEntry::make('rating')->label(__('التقييم'))->placeholder('-')->color('warning')
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))
                    ->visible(fn (MaintenanceRequest $r) => $r->rating !== null),
            ]),
            Section::make(__('الصور'))->icon(Heroicon::OutlinedPhoto)
                ->visible(fn (MaintenanceRequest $r) => $r->getMedia('photos')->isNotEmpty() || $r->getMedia('after')->isNotEmpty())
                ->schema([
                    ImageEntry::make('photo_urls')->label(__('قبل'))->imageHeight(120)->state(fn (MaintenanceRequest $r) => $r->photoUrls())
                        ->extraImgAttributes(['class' => 'rounded-xl object-cover', 'loading' => 'lazy'])
                        ->visible(fn (MaintenanceRequest $r) => $r->getMedia('photos')->isNotEmpty()),
                    ImageEntry::make('after_urls')->label(__('بعد'))->imageHeight(120)->state(fn (MaintenanceRequest $r) => $r->photoUrls('after'))
                        ->extraImgAttributes(['class' => 'rounded-xl object-cover', 'loading' => 'lazy'])
                        ->visible(fn (MaintenanceRequest $r) => $r->getMedia('after')->isNotEmpty()),
                ]),
        ]);
    }

    /** Owners see what the work costs them; a charge to the tenant reaches them as an instalment instead. */
    protected static function showsCost(): bool
    {
        return true;
    }

    /** @return list<TextColumn> */
    protected static function portalColumns(): array
    {
        return [
            TextColumn::make('title')->label(__('الطلب'))->weight('bold')->wrap()
                ->description(fn (MaintenanceRequest $r) => $r->code.' · '.$r->unit?->title),
            TextColumn::make('category')->label(__('النوع'))->badge()->color('gray')->visibleFrom('md'),
            TextColumn::make('status')->label(__('الحالة'))->badge()
                ->description(fn (MaintenanceRequest $r) => $r->scheduled_at && $r->status->isOpen() ? $r->scheduled_at->format('Y-m-d H:i') : null),
            TextColumn::make('created_at')->label(__('فُتح'))->since()->sortable()->visibleFrom('md'),
        ];
    }
}
