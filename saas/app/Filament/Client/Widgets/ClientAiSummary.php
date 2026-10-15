<?php

namespace App\Filament\Client\Widgets;

use App\Filament\Widgets\PortalAiSummary;

class ClientAiSummary extends PortalAiSummary
{
    protected function assistantPage(): string
    {
        return \App\Filament\Client\Pages\Assistant::class;
    }

    protected function instruction(): string
    {
        return 'Write a short, friendly summary for this tenant/buyer about their own contracts and payments: next payment and its date, '
            .'anything overdue, what is left this year, when their contract ends, and open support requests. 3-5 bullets with the key numbers, '
            .'then one helpful reminder. Under 120 words, no tables, no preamble.';
    }

    protected function emptyText(): string
    {
        return __('اضغط "توليد الرؤى" ليلخّص لك المساعد عقدك ودفعاتك القادمة وأي مبالغ متأخرة.');
    }
}
