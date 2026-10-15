<?php

namespace App\Filament\Concerns;

use App\Exceptions\BusinessRuleException;
use App\Support\PlanLimits;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;

/** Create pages of records the plan counts (units, staff users): stop before the form is filled, and again before saving. */
trait EnforcesPlanQuota
{
    abstract protected static function planQuota(): string;

    public function mount(): void
    {
        if (PlanLimits::reached(static::planQuota())) {
            $this->notifyQuota();
            $this->redirect(static::getResource()::getUrl('index'));

            return;
        }
        parent::mount();
    }

    protected function beforeCreate(): void
    {
        try {
            PlanLimits::ensureRoomFor(static::planQuota());
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();
            $this->halt();
        }
    }

    private function notifyQuota(): void
    {
        try {
            PlanLimits::ensureRoomFor(static::planQuota());
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->warning()->persistent()->send();
        }
    }

    /** The list page's "create" button, disabled with the reason when the plan is full. */
    public static function quotaAwareCreateAction(string $quota): CreateAction
    {
        return CreateAction::make()
            ->disabled(fn () => PlanLimits::reached($quota))
            ->tooltip(fn () => PlanLimits::reached($quota) ? __('وصلت إلى حد باقتك. اطلب الترقية من صفحة الاشتراك.') : null);
    }
}
