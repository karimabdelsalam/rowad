<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Every notification the legacy `notification` module could send.
 * Each event has an editable MessageTemplate (email + SMS) per tenant.
 */
enum NotificationEvent: string implements HasLabel
{
    case RentDueIn15Days = 'rent_due_15';           // payRent15
    case SaleInstallmentDueIn15Days = 'sale_due_15'; // sellPay15
    case SaleInstallmentDueTomorrow = 'sale_due_1';  // sellPay1
    case SaleInstallmentDueToday = 'sale_due_0';     // sellPay0
    case SaleInstallmentOverdue = 'sale_overdue';    // sellPayx
    case SaleInstallmentCollected = 'sale_collected'; // collectSellPart
    case ContractEndedOwner = 'contract_ended_owner';   // finishBuildOwner
    case ContractEndedRenter = 'contract_ended_renter'; // finishBuildRenter
    case OwnerUnitRented = 'owner_unit_rented';      // ownerBuildRented
    case OwnerPayout = 'owner_payout';               // ownerAmountTo
    case NewRentContract = 'new_rent_contract';      // newRentContract
    case EmployeeIdExpiring = 'employee_id_expiring'; // visaEnd15
    case LicenseExpiring = 'license_expiring';       // licenseEnd15
    case PasswordResetOtp = 'password_reset_otp';    // otpReset
    case LoginOtp = 'login_otp';                     // smsLogin
    case AccountPasswordReset = 'account_password_reset'; // resetAccountPWD
    case PasswordRecovery = 'password_recovery';     // recoverPWD
    case EmailChanged = 'email_changed';             // updateEmail
    case NewRegistration = 'new_registration';       // newRegister

    public static function fromLegacy(string $action): self
    {
        return match ($action) {
            'payRent15' => self::RentDueIn15Days,
            'sellPay15' => self::SaleInstallmentDueIn15Days,
            'sellPay1' => self::SaleInstallmentDueTomorrow,
            'sellPay0' => self::SaleInstallmentDueToday,
            'sellPayx' => self::SaleInstallmentOverdue,
            'collectSellPart' => self::SaleInstallmentCollected,
            'finishBuildOwner' => self::ContractEndedOwner,
            'finishBuildRenter' => self::ContractEndedRenter,
            'ownerBuildRented' => self::OwnerUnitRented,
            'ownerAmountTo' => self::OwnerPayout,
            'newRentContract' => self::NewRentContract,
            'visaEnd15' => self::EmployeeIdExpiring,
            'licenseEnd15' => self::LicenseExpiring,
            'otpReset' => self::PasswordResetOtp,
            'smsLogin' => self::LoginOtp,
            'resetAccountPWD' => self::AccountPasswordReset,
            'recoverPWD' => self::PasswordRecovery,
            'updateEmail' => self::EmailChanged,
            'newRegister' => self::NewRegistration,
        };
    }

    /** Placeholders available in the template body, written as {name}. */
    public function variables(): array
    {
        return match ($this) {
            self::RentDueIn15Days, self::SaleInstallmentDueIn15Days, self::SaleInstallmentDueTomorrow,
            self::SaleInstallmentDueToday, self::SaleInstallmentOverdue, self::SaleInstallmentCollected
                => ['name', 'unit', 'amount', 'due_date', 'contract_number'],
            self::ContractEndedOwner => ['name', 'unit', 'location', 'renters'],
            self::ContractEndedRenter => ['name', 'unit', 'end_date'],
            self::OwnerUnitRented => ['name', 'unit', 'renters', 'start_date', 'end_date', 'amount'],
            self::OwnerPayout => ['name', 'amount', 'date', 'voucher_number'],
            self::NewRentContract => ['name', 'unit', 'start_date', 'end_date', 'amount', 'contract_number'],
            self::EmployeeIdExpiring => ['name', 'visa_number', 'expire_date'],
            self::LicenseExpiring => ['license', 'expire_date'],
            self::PasswordResetOtp, self::LoginOtp => ['name', 'code'],
            self::AccountPasswordReset => ['name', 'username', 'password', 'login_url'],
            self::PasswordRecovery => ['name', 'reset_url'],
            self::EmailChanged => ['name', 'email'],
            self::NewRegistration => ['name', 'username', 'login_url'],
        };
    }

    public function getLabel(): string
    {
        return __(match ($this) {
            self::RentDueIn15Days => 'تنبيه استحقاق قسط إيجار قبل 15 يوم',
            self::SaleInstallmentDueIn15Days => 'تنبيه استحقاق قسط بيع قبل 15 يوم',
            self::SaleInstallmentDueTomorrow => 'تنبيه استحقاق قسط بيع غدًا',
            self::SaleInstallmentDueToday => 'تنبيه استحقاق قسط بيع اليوم',
            self::SaleInstallmentOverdue => 'تنبيه تأخر سداد قسط بيع',
            self::SaleInstallmentCollected => 'تأكيد تحصيل قسط بيع',
            self::ContractEndedOwner => 'انتهاء عقد إيجار (للمالك)',
            self::ContractEndedRenter => 'انتهاء عقد إيجار (للمستأجر)',
            self::OwnerUnitRented => 'تأجير وحدة المالك',
            self::OwnerPayout => 'تحويل مبلغ للمالك',
            self::NewRentContract => 'عقد إيجار جديد',
            self::EmployeeIdExpiring => 'قرب انتهاء إقامة/هوية موظف',
            self::LicenseExpiring => 'قرب انتهاء رخصة حكومية',
            self::PasswordResetOtp => 'رمز استعادة كلمة المرور',
            self::LoginOtp => 'رمز الدخول',
            self::AccountPasswordReset => 'إعادة تعيين بيانات الدخول',
            self::PasswordRecovery => 'استعادة كلمة المرور',
            self::EmailChanged => 'تغيير البريد الإلكتروني',
            self::NewRegistration => 'تسجيل حساب جديد',
        });
    }
}
