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
                Section::make(__('الوظيفة'))->icon(Heroicon::OutlinedBriefcase)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('job_title')->label(__('المسمى الوظيفي'))->placeholder('-')->weight('bold'),
                    TextEntry::make('department')->label(__('القسم'))->placeholder('-'),
                    TextEntry::make('status')->label(__('الحالة'))->badge()
                        ->formatStateUsing(fn ($state) => ['active' => __('على رأس العمل'), 'on_leave' => __('في إجازة'), 'terminated' => __('منتهي الخدمة')][$state] ?? $state)
                        ->color(fn ($state) => ['active' => 'success', 'on_leave' => 'warning', 'terminated' => 'danger'][$state] ?? 'gray'),
                    TextEntry::make('employment_type')->label(__('نوع التوظيف'))->badge()->color('gray')
                        ->formatStateUsing(fn ($state) => ['full_time' => __('دوام كامل'), 'part_time' => __('دوام جزئي'), 'contract' => __('عقد مؤقت')][$state] ?? $state),
                    Parts::date('start_date', 'تاريخ التعيين'),
                    TextEntry::make('tenure')->label(__('مدة الخدمة'))->placeholder('-')
                        ->state(fn (Employee $r) => $r->start_date?->locale(app()->getLocale())->diffForHumans(today(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])),
                    Parts::date('contract_end_date', 'نهاية العقد'),
                    TextEntry::make('weekly_hours')->label(__('ساعات العمل الأسبوعية'))->numeric(),
                    TextEntry::make('education')->label(__('المؤهل'))->placeholder('-'),
                    TextEntry::make('marital_status')->label(__('الحالة الاجتماعية'))->placeholder('-'),
                ]),
                Section::make(__('الراتب والتأمينات'))->icon(Heroicon::OutlinedBanknotes)->columns(['default' => 2, 'md' => 4])->schema([
                    Parts::money('salary', 'الراتب الأساسي')->weight('bold'),
                    Parts::money('housing_allowance', 'بدل السكن'),
                    Parts::money('transport_allowance', 'بدل النقل'),
                    Parts::money('other_allowances', 'بدلات أخرى'),
                    TextEntry::make('total_wage')->label(__('الأجر الشامل'))->weight('bold')->color('success')->state(fn (Employee $r) => Parts::amount($r->totalWage())),
                    TextEntry::make('gosi_employee')->label(__('التأمينات — حصة الموظف'))->state(fn (Employee $r) => Parts::amount(\App\Services\Hr\Gosi::contributions($r)['employee']))
                        ->helperText(fn (Employee $r) => $r->gosi_subscribed ? \App\Services\Hr\Gosi::contributions($r)['employee_rate'].' %' : __('غير مشترك')),
                    TextEntry::make('gosi_employer')->label(__('التأمينات — حصة المنشأة'))->state(fn (Employee $r) => Parts::amount(\App\Services\Hr\Gosi::contributions($r)['employer']))
                        ->helperText(fn (Employee $r) => $r->gosi_subscribed ? \App\Services\Hr\Gosi::contributions($r)['employer_rate'].' %' : null),
                    TextEntry::make('gosi_number')->label(__('رقم التأمينات'))->placeholder('-')->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('bank.title')->label(__('البنك'))->placeholder('-'),
                    TextEntry::make('iban')->label(__('الآيبان'))->placeholder('-')->copyable()->extraAttributes(['dir' => 'ltr'])->fontFamily('mono')->size('xs')->columnSpan(2),
                    TextEntry::make('commissions')->label(__('العمولات'))->state(fn (Employee $r) => __('إيجار :r% · بيع :s%', ['r' => (float) $r->rent_commission, 's' => (float) $r->sale_commission])),
                ]),
                Section::make(__('الإجازات ونهاية الخدمة'))->icon(Heroicon::OutlinedCalendarDays)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('leave_entitlement')->label(__('رصيد السنة'))->state(fn (Employee $r) => trans_choice('{1} يوم واحد|[2,*] :count يومًا', \App\Services\Hr\LeaveBalance::entitlement($r))),
                    TextEntry::make('leave_used')->label(__('المستخدم'))->state(fn (Employee $r) => trans_choice('{0} لا شيء|{1} يوم واحد|[2,*] :count يومًا', \App\Services\Hr\LeaveBalance::used($r))),
                    TextEntry::make('leave_left')->label(__('المتبقي'))->weight('bold')->color('success')->state(fn (Employee $r) => trans_choice('{0} لا شيء|{1} يوم واحد|[2,*] :count يومًا', \App\Services\Hr\LeaveBalance::remaining($r))),
                    TextEntry::make('eosb')->label(__('مكافأة نهاية الخدمة اليوم'))->weight('bold')
                        ->state(fn (Employee $r) => Parts::amount(\App\Services\Hr\EndOfService::calculate($r, now(), \App\Enums\EndOfServiceReason::Termination)['award']))
                        ->helperText(__('بافتراض الإنهاء من صاحب العمل (الكاملة)')),
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
