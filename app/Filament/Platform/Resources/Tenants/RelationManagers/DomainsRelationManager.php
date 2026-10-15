<?php

namespace App\Filament\Platform\Resources\Tenants\RelationManagers;

use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

/** The organisation's addresses: its subdomain on the platform, plus its own domain when the plan includes one. */
class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('النطاقات');
    }

    public static function isPlatformSubdomain(string $domain): bool
    {
        return Str::endsWith($domain, '.'.config('rowad.tenancy.base_domain'));
    }

    /** "https://Alrowad.com.sa/" -> "alrowad.com.sa" */
    public static function normalise(string $domain): string
    {
        return Str::of($domain)->lower()->trim()->replaceMatches('#^https?://#', '')->before('/')->value();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('domain')
                ->label(__('النطاق'))
                ->required()
                ->maxLength(190)
                ->placeholder('alrowad.com.sa')
                ->extraInputAttributes(['dir' => 'ltr'])
                ->dehydrateStateUsing(fn (string $state) => self::normalise($state))
                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                    $domain = self::normalise((string) $value);
                    if (! preg_match('/^(?!-)([a-z0-9-]{1,63}(?<!-)\.)+[a-z]{2,}$/', $domain) || in_array($domain, config('tenancy.central_domains'), true)) {
                        $fail(__('صيغة النطاق غير صالحة.'));
                    } elseif (Domain::where('domain', $domain)->exists()) {
                        $fail(__('هذا النطاق مستخدم لمؤسسة أخرى أو مضاف بالفعل.'));
                    }
                })
                ->helperText(fn () => __('أضف في لوحة النطاق سجل CNAME يشير إلى :target، ثم أصدر شهادة SSL للنطاق على السيرفر.', ['target' => config('rowad.tenancy.base_domain')])
                    .' '.($this->getOwnerRecord()->plan?->hasFeature('custom_domain') ? '' : __('تنبيه: باقة المؤسسة الحالية لا تشمل النطاق الخاص.'))),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                TextColumn::make('domain')->label(__('النطاق'))->copyable()
                    ->url(fn ($record) => request()->getScheme().'://'.$record->domain, shouldOpenInNewTab: true),
                TextColumn::make('type')->label(__('النوع'))->badge()
                    ->state(fn ($record) => self::isPlatformSubdomain($record->domain) ? __('نطاق المنصة') : __('نطاق خاص'))
                    ->color(fn ($record) => self::isPlatformSubdomain($record->domain) ? 'gray' : 'success'),
                TextColumn::make('created_at')->label(__('تاريخ الإضافة'))->date('Y-m-d'),
            ])
            ->headerActions([
                CreateAction::make()->label(__('إضافة نطاق خاص'))->modalHeading(__('إضافة نطاق خاص'))->createAnother(false),
            ])
            ->recordActions([
                // The platform subdomain is the office's permanent address (links, emails, QR sheets): it is never removed.
                DeleteAction::make()->visible(fn ($record) => ! self::isPlatformSubdomain($record->domain)),
            ]);
    }
}
