<?php

namespace App\Filament\Infolists;

use App\Support\Settings;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Building blocks shared by the record view pages: a header card with the record's identity and
 * headline figures, money entries, person links and the "record" footer (who created it, when).
 */
final class Parts
{
    /**
     * @param  \Closure(Model): array{kicker?: string, title: string, sub?: list<string|null>, facts?: list<array{label: string, value: string, tone?: string}>}  $data
     */
    public static function header(Heroicon $icon, \Closure $data): ViewEntry
    {
        return ViewEntry::make('record_header')->hiddenLabel()->columnSpanFull()
            ->view('filament.infolists.record-header')
            ->viewData(['icon' => $icon, 'resolve' => $data]);
    }

    public static function money(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)->label(__($label))->numeric(2)->suffix(' '.Settings::currencyLabel())->placeholder('-');
    }

    public static function date(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)->label(__($label))->date('Y-m-d')->placeholder('-');
    }

    public static function link(string $name, string $label, \Closure $url): TextEntry
    {
        return TextEntry::make($name)->label(__($label))->weight('bold')->color('primary')->placeholder('-')->url($url);
    }

    public static function phone(string $name, string $label = 'الجوال'): TextEntry
    {
        return TextEntry::make($name)->label(__($label))->placeholder('-')->icon(Heroicon::OutlinedPhone)->iconColor('gray')
            ->url(fn ($state) => filled($state) ? 'tel:'.preg_replace('/\s+/', '', (string) $state) : null)->extraAttributes(['dir' => 'ltr']);
    }

    public static function email(string $name = 'email', string $label = 'البريد الإلكتروني'): TextEntry
    {
        return TextEntry::make($name)->label(__($label))->placeholder('-')->icon(Heroicon::OutlinedEnvelope)->iconColor('gray')->copyable()
            ->url(fn ($state) => filled($state) ? 'mailto:'.$state : null)->extraAttributes(['dir' => 'ltr']);
    }

    /** Identity document block shared by owners, clients and employees. */
    public static function identity(): Section
    {
        return Section::make(__('الهوية'))->icon(Heroicon::OutlinedIdentification)->columns(['default' => 2, 'md' => 4])->schema([
            TextEntry::make('id_type')->label(__('نوع الهوية'))->badge()->color('gray')->placeholder('-'),
            TextEntry::make('id_number')->label(__('رقم الهوية'))->placeholder('-')->copyable()->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('id_source')->label(__('مصدرها'))->placeholder('-'),
            self::date('id_issue_date', 'تاريخ الإصدار'),
            self::date('birth_date', 'تاريخ الميلاد'),
            TextEntry::make('nationality')->label(__('الجنسية'))->placeholder('-'),
        ]);
    }

    /** Phone / email / address block shared by owners, clients and employees. */
    public static function contact(): Section
    {
        return Section::make(__('الاتصال'))->icon(Heroicon::OutlinedPhone)->columns(['default' => 2, 'md' => 4])->schema([
            self::phone('mobile'),
            self::phone('home_phone', 'هاتف المنزل'),
            self::phone('work_phone', 'هاتف العمل'),
            self::email(),
            TextEntry::make('work')->label(__('جهة العمل'))->placeholder('-')->columnSpan(2),
            TextEntry::make('address')->label(__('العنوان'))->placeholder('-')->columnSpan(2),
        ]);
    }

    /** Portal login card for owners/clients: whether they can sign in and when they last did. */
    public static function portalAccount(): Section
    {
        return Section::make(__('حساب البوابة'))->icon(Heroicon::OutlinedKey)->columns(2)->schema([
            TextEntry::make('portal_state')->label(__('الحالة'))->badge()
                ->state(fn (Model $r) => $r->user ? ($r->user->is_active ? __('مفعل') : __('موقوف')) : __('لا يوجد حساب'))
                ->color(fn (Model $r) => $r->user ? ($r->user->is_active ? 'success' : 'danger') : 'gray'),
            TextEntry::make('user.email')->label(__('اسم الدخول'))->placeholder('-')
                ->state(fn (Model $r) => $r->user?->email ?? $r->user?->username)->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('user.last_login_at')->label(__('آخر دخول'))->dateTime('Y-m-d H:i')->placeholder(__('لم يدخل بعد'))->columnSpanFull(),
        ]);
    }

    /** @return list<TextEntry> the contract's Ejar documentation as tenants and owners see it */
    public static function ejar(): array
    {
        return [
            TextEntry::make('ejar_status')->label(__('التوثيق في إيجار'))->badge(),
            TextEntry::make('ejar_number')->label(__('رقم العقد في إيجار'))->copyable()->weight('bold')->visible(fn (Model $r) => filled($r->ejar_number)),
            TextEntry::make('ejar_pdf')->label(__('العقد الموثق'))->color('primary')->icon(Heroicon::OutlinedDocumentArrowDown)->columnSpanFull()
                ->state(fn (Model $r) => $r->getFirstMedia('ejar') ? __('تنزيل نسخة إيجار (PDF)') : null)
                ->url(fn (Model $r) => ($media = $r->getFirstMedia('ejar')) ? route('site.media', ['media' => $media]) : null, true)
                ->visible(fn (Model $r) => $r->getFirstMedia('ejar') !== null),
        ];
    }

    /** A plain stat tile used inside the header `facts`. */
    public static function fact(string $label, string|int|float|null $value, ?string $tone = null): array
    {
        return ['label' => __($label), 'value' => (string) ($value ?? '-')] + ($tone ? ['tone' => $tone] : []);
    }

    public static function amount(float|string|null $v): string
    {
        return number_format((float) $v, 2).' '.Settings::currencyLabel();
    }

    /** Who created the record and when; collapsed since it is rarely what the user came for. */
    public static function record(bool $withCreator = true): Section
    {
        return Section::make(__('السجل'))->icon(Heroicon::OutlinedClock)->collapsed()->columns(['default' => 2, 'md' => 3])->schema(array_values(array_filter([
            $withCreator ? TextEntry::make('creator.name')->label(__('أنشأه'))->placeholder('-') : null,
            TextEntry::make('created_at')->label(__('تاريخ الإنشاء'))->dateTime('Y-m-d H:i'),
            TextEntry::make('updated_at')->label(__('آخر تعديل'))->dateTime('Y-m-d H:i'),
        ])));
    }
}
