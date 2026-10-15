<?php

namespace App\Filament\Admin\Resources\Employees\Schemas;

use App\Filament\Infolists\Parts;
use App\Models\Employee;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Employee card: job, pay and commissions, identity (with expiry warning), penalties and contact. */
class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedBriefcase, function (Employee $r) {
                $expiry = $r->id_expiry_date;
                $expiryTone = $expiry === null ? null : ($expiry->isPast() ? 'danger' : ($expiry->diffInDays(today()) <= 60 ? 'warning' : 'success'));

                return [
                    'kicker' => __('موظف').($r->user ? ' · '.($r->user->is_active ? __('حساب مفعل') : __('حساب موقوف')) : ''),
                    'title' => $r->full_name,
                    'sub' => [$r->work, $r->mobile, $r->start_date ? __('منذ :date', ['date' => $r->start_date->format('Y-m-d')]) : null],
                    'facts' => [
                        Parts::fact('الراتب', Parts::amount($r->salary)),
                        Parts::fact('انتهاء الهوية', $expiry?->format('Y-m-d') ?? '-', $expiryTone),
                        Parts::fact('الجزاءات', $r->penalties->count(), $r->penalties->isNotEmpty() ? 'warning' : null),
                    ],
                ];
            }),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الوظيفة والراتب'))->icon(Heroicon::OutlinedBanknotes)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('work')->label(__('الوظيفة'))->placeholder('-')->weight('bold')->columnSpan(2),
                    Parts::date('start_date', 'تاريخ التعيين'),
                    TextEntry::make('tenure')->label(__('مدة الخدمة'))->placeholder('-')
                        ->state(fn (Employee $r) => $r->start_date?->locale(app()->getLocale())->diffForHumans(today(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])),
                    Parts::money('salary', 'الراتب الشهري')->weight('bold'),
                    TextEntry::make('rent_commission')->label(__('عمولة الإيجار'))->numeric(2)->suffix(' %')->placeholder('-'),
                    TextEntry::make('sale_commission')->label(__('عمولة البيع'))->numeric(2)->suffix(' %')->placeholder('-'),
                    TextEntry::make('education')->label(__('المؤهل'))->placeholder('-'),
                    TextEntry::make('marital_status')->label(__('الحالة الاجتماعية'))->placeholder('-'),
                ]),
                Section::make(__('الجزاءات'))->icon(Heroicon::OutlinedExclamationTriangle)
                    ->visible(fn (Employee $r) => $r->penalties->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('penalties')->hiddenLabel()->columns(['default' => 2, 'md' => 4])->schema([
                            Parts::date('issued_at', 'التاريخ'),
                            Parts::money('amount', 'المبلغ')->color('danger'),
                            TextEntry::make('reason')->label(__('السبب'))->placeholder('-')->columnSpan(2),
                        ]),
                    ]),
                Parts::identity()->schema([
                    TextEntry::make('id_type')->label(__('نوع الهوية'))->badge()->color('gray')->placeholder('-'),
                    TextEntry::make('id_number')->label(__('رقم الهوية'))->placeholder('-')->copyable()->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('id_source')->label(__('مصدرها'))->placeholder('-'),
                    TextEntry::make('nationality')->label(__('الجنسية'))->placeholder('-'),
                    Parts::date('id_issue_date', 'تاريخ الإصدار'),
                    Parts::date('id_expiry_date', 'تاريخ الانتهاء')->weight('bold')
                        ->color(fn (Employee $r) => $r->id_expiry_date?->isPast() ? 'danger' : ($r->id_expiry_date && $r->id_expiry_date->diffInDays(today()) <= 60 ? 'warning' : null)),
                ]),
                Parts::contact()->schema([
                    Parts::phone('mobile'),
                    Parts::phone('mobile2', 'جوال آخر'),
                    Parts::phone('home_phone', 'هاتف المنزل'),
                    Parts::phone('work_phone', 'هاتف العمل'),
                    Parts::email()->columnSpan(2),
                    TextEntry::make('address')->label(__('العنوان'))->placeholder('-')->columnSpan(2),
                    TextEntry::make('address2')->label(__('عنوان آخر'))->placeholder('-')->columnSpanFull(),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('حساب الدخول'))->icon(Heroicon::OutlinedKey)->columns(2)->schema([
                    TextEntry::make('account_state')->label(__('الحالة'))->badge()
                        ->state(fn (Employee $r) => $r->user ? ($r->user->is_active ? __('مفعل') : __('موقوف')) : __('لا يوجد حساب'))
                        ->color(fn (Employee $r) => $r->user ? ($r->user->is_active ? 'success' : 'danger') : 'gray'),
                    TextEntry::make('user.email')->label(__('اسم الدخول'))->placeholder('-')
                        ->state(fn (Employee $r) => $r->user?->email ?? $r->user?->username)->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('roles')->label(__('الأدوار'))->badge()->color('info')->placeholder('-')
                        ->state(fn (Employee $r) => $r->user?->getRoleNames()->all())->columnSpanFull(),
                    TextEntry::make('user.last_login_at')->label(__('آخر دخول'))->dateTime('Y-m-d H:i')->placeholder(__('لم يدخل بعد'))->columnSpanFull(),
                ]),
                Section::make(__('إعدادات'))->icon(Heroicon::OutlinedCog6Tooth)->columns(2)->schema([
                    TextEntry::make('calendar')->label(__('التقويم'))->badge()->color('gray'),
                ]),
                Parts::record(false),
            ]),
        ]);
    }
}
