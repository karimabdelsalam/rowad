<?php

namespace App\Services\Messaging;

use App\Enums\InstallmentType;
use App\Enums\NotificationEvent as E;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GovernmentLicense;
use App\Models\Installment;
use App\Models\Owner;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\Unit;
use App\Support\Hijri;
use App\Support\Settings;
use Carbon\CarbonInterface;

/** Business moments that send notifications (legacy sendNotify call sites). */
class DomainNotifications
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /** Legacy rent::add → newRentContract (renters) + ownerBuildRented (owners). */
    public function rentContractCreated(RentContract $contract): void
    {
        $contract->loadMissing(['unit.owners', 'clients']);
        $vars = [
            'unit' => $contract->unit->title,
            'contract_number' => $contract->number,
            'start_date' => $this->date($contract->start_date, $contract->calendar?->value),
            'end_date' => $this->date($contract->end_date, $contract->calendar?->value),
            'amount' => $this->money($contract->rent_amount),
            'renters' => $contract->clients->pluck('full_name')->join('، '),
        ];

        $this->notifier->send(E::NewRentContract, $vars, $contract->clients->map(fn (Client $c) => $this->person($c))->all());
        $this->notifier->send(E::OwnerUnitRented, $vars, $contract->unit->owners->map(fn (Owner $o) => $this->person($o))->all());
    }

    /** Legacy statementsin::add → collectSellPart when sale installments are receipted. */
    public function receiptIssued(ReceiptVoucher $voucher): void
    {
        $sale = Installment::with(['unit', 'contract.clients'])->where('receipt_voucher_id', $voucher->id)->get()
            ->filter(fn (Installment $i) => ! $i->type->isRent() && $i->type !== InstallmentType::Expenses);

        foreach ($sale as $installment) {
            $this->notifier->send(E::SaleInstallmentCollected, $this->installmentVars($installment),
                $installment->contract->clients->map(fn (Client $c) => $this->person($c))->all());
        }
    }

    /** Legacy statementsout::add → ownerAmountTo when an owner is paid. */
    public function paymentIssued(PaymentVoucher $voucher): void
    {
        if ($voucher->party_type !== 'owner' || ! ($owner = $voucher->party) instanceof Owner) {
            return;
        }
        $this->notifier->send(E::OwnerPayout, [
            'amount' => $this->money($voucher->amount),
            'date' => $this->date($voucher->issued_at, $voucher->calendar?->value),
            'voucher_number' => $voucher->number,
        ], [$this->person($owner)]);
    }

    /** Legacy owner/buyer reset → recoverPWD with the new credentials. */
    public function credentialsIssued(Owner|Client $person, string $login, string $password, string $panel): void
    {
        $this->notifier->send(E::AccountPasswordReset, [
            'username' => $login,
            'password' => $password,
            'login_url' => url("/{$panel}"),
        ], [$this->person($person)]);
    }

    public function installmentReminder(E $event, Installment $installment): void
    {
        $installment->loadMissing(['unit', 'contract.clients']);
        $this->notifier->send($event, $this->installmentVars($installment),
            $installment->contract->clients->map(fn (Client $c) => $this->person($c))->all());
    }

    /** Legacy visaEnd15 → office email/mobile + staff alert. */
    public function employeeIdExpiring(Employee $employee): void
    {
        $this->notifier->send(E::EmployeeIdExpiring, [
            'name' => $employee->full_name,
            'visa_number' => $employee->id_number,
            'expire_date' => $this->date($employee->id_expiry_date, $employee->calendar?->value),
        ], [$this->office()], 'employees.view_any');
    }

    /** Legacy licenseEnd15. */
    public function licenseExpiring(GovernmentLicense $license): void
    {
        $this->notifier->send(E::LicenseExpiring, [
            'license' => $license->title,
            'expire_date' => $this->date($license->expiry_date, $license->calendar?->value),
        ], [$this->office()], 'government_licenses.view_any');
    }

    private function installmentVars(Installment $installment): array
    {
        return [
            'unit' => $installment->unit->title,
            'amount' => $this->money($installment->remainingAmount()),
            'due_date' => $this->date($installment->due_date, $installment->calendar?->value),
            'contract_number' => $installment->contract?->number,
        ];
    }

    /** @return array{email: ?string, mobile: ?string, name: string} */
    private function person(Owner|Client $person): array
    {
        return ['email' => $person->email, 'mobile' => $person->mobile, 'name' => $person instanceof Client ? $person->displayName() : $person->full_name];
    }

    private function office(): array
    {
        return ['email' => Settings::get('email'), 'mobile' => Settings::get('mobile'), 'name' => Settings::get('site_name')];
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2).' '.Settings::currencyLabel();
    }

    private function date(?CarbonInterface $date, ?string $calendar): string
    {
        if ($date === null) {
            return '';
        }

        return $calendar === 'hijri' ? Hijri::toString($date) : $date->toDateString();
    }
}
