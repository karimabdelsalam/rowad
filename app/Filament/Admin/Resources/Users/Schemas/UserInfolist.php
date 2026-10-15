<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Enums\UserType;
use App\Filament\Admin\Resources\Clients\ClientResource;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Filament\Infolists\Parts;
use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Login account: who it belongs to, what it may do, and its recent sign-ins. */
class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedUserCircle, fn (User $r) => [
                'kicker' => ($r->type?->getLabel() ?? __('مستخدم')).($r->is_super_admin ? ' · '.__('مدير النظام') : ''),
                'title' => $r->name,
                'sub' => [$r->email, $r->username ? '@'.$r->username : null, $r->mobile],
                'facts' => [
                    Parts::fact('الحالة', $r->is_active ? __('مفعل') : __('موقوف'), $r->is_active ? 'success' : 'danger'),
                    Parts::fact('آخر دخول', $r->last_login_at?->locale(app()->getLocale())->diffForHumans() ?? __('لم يدخل بعد')),
                    Parts::fact('الأدوار', $r->is_super_admin ? __('كل الصلاحيات') : ($r->getRoleNames()->implode('، ') ?: '-')),
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الحساب'))->icon(Heroicon::OutlinedIdentification)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('name')->label(__('الاسم'))->weight('bold')->columnSpan(2),
                    TextEntry::make('type')->label(__('النوع'))->badge(),
                    TextEntry::make('locale')->label(__('اللغة'))->badge()->color('gray')
                        ->formatStateUsing(fn ($state) => $state === 'ar' ? __('العربية') : __('English')),
                    Parts::email(),
                    TextEntry::make('username')->label(__('اسم المستخدم'))->placeholder('-')->extraAttributes(['dir' => 'ltr'])->copyable(),
                    Parts::phone('mobile'),
                    TextEntry::make('national_id')->label(__('رقم الهوية'))->placeholder('-')->extraAttributes(['dir' => 'ltr']),
                ]),
                Section::make(__('الصلاحيات'))->icon(Heroicon::OutlinedShieldCheck)->columns(['default' => 2, 'md' => 4])->schema([
                    IconEntry::make('is_active')->label(__('مفعل'))->boolean(),
                    IconEntry::make('is_super_admin')->label(__('مدير النظام'))->boolean(),
                    IconEntry::make('sms_login_enabled')->label(__('دخول برمز SMS'))->boolean(),
                    TextEntry::make('email_verified_at')->label(__('تأكيد البريد'))->dateTime('Y-m-d')->placeholder(__('غير مؤكد')),
                    TextEntry::make('roles')->label(__('الأدوار'))->badge()->color('info')->placeholder(__('بدون أدوار'))
                        ->state(fn (User $r) => $r->getRoleNames()->all())->columnSpanFull(),
                    TextEntry::make('permissions_count')->label(__('الصلاحيات المباشرة'))->columnSpanFull()
                        ->state(fn (User $r) => $r->is_super_admin ? __('كل الصلاحيات (مدير النظام)') : trans_choice('{0} لا توجد صلاحيات|{1} صلاحية واحدة|{2} صلاحيتان|[3,*] :count صلاحية', $r->getAllPermissions()->count())),
                ]),
                Section::make(__('آخر عمليات الدخول'))->icon(Heroicon::OutlinedClock)
                    ->visible(fn (User $r) => $r->loginActivities()->exists())
                    ->schema([
                        RepeatableEntry::make('recent_logins')->hiddenLabel()->columns(['default' => 2, 'md' => 4])
                            ->state(fn (User $r) => $r->loginActivities()->latest()->limit(8)->get())
                            ->schema([
                                TextEntry::make('created_at')->label(__('الوقت'))->dateTime('Y-m-d H:i'),
                                TextEntry::make('ip_address')->label('IP')->placeholder('-')->fontFamily('mono')->size('xs'),
                                TextEntry::make('user_agent')->label(__('الجهاز'))->placeholder('-')->limit(50)->columnSpan(2),
                            ]),
                    ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('مرتبط بـ'))->icon(Heroicon::OutlinedLink)->schema([
                    Parts::link('owner.full_name', 'ملف المالك', fn (User $r) => $r->owner ? OwnerResource::getUrl('view', ['record' => $r->owner]) : null)
                        ->visible(fn (User $r) => $r->owner !== null),
                    Parts::link('client.full_name', 'ملف العميل', fn (User $r) => $r->client ? ClientResource::getUrl('view', ['record' => $r->client]) : null)
                        ->visible(fn (User $r) => $r->client !== null),
                    Parts::link('employee.full_name', 'ملف الموظف', fn (User $r) => $r->employee ? EmployeeResource::getUrl('view', ['record' => $r->employee]) : null)
                        ->visible(fn (User $r) => $r->employee !== null),
                    TextEntry::make('no_profile')->hiddenLabel()->placeholder(__('حساب موظفي بدون ملف مرتبط'))
                        ->visible(fn (User $r) => $r->owner === null && $r->client === null && $r->employee === null)->state(null),
                ]),
                Section::make(__('النشاط'))->icon(Heroicon::OutlinedBolt)->columns(2)->schema([
                    TextEntry::make('last_login_at')->label(__('آخر دخول'))->dateTime('Y-m-d H:i')->placeholder(__('لم يدخل بعد')),
                    TextEntry::make('logins_count')->label(__('عدد الدخولات'))->numeric()->state(fn (User $r) => $r->loginActivities()->count()),
                    TextEntry::make('tasks_count')->label(__('مهام مسندة'))->numeric()->state(fn (User $r) => $r->assignedTasks()->count()),
                    TextEntry::make('devices_count')->label(__('أجهزة الجوال'))->numeric()->state(fn (User $r) => $r->deviceTokens()->count()),
                ]),
                Parts::record(false),
            ]),
        ]);
    }
}
