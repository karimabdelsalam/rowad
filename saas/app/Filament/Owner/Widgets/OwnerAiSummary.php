<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Widgets\PortalAiSummary;

class OwnerAiSummary extends PortalAiSummary
{
    protected function assistantPage(): string
    {
        return \App\Filament\Owner\Pages\Assistant::class;
    }

    protected function instruction(): string
    {
        return 'Write a short summary for this property owner about their own units: what they are owed, what they received this year, '
            .'occupancy of their units, overdue rent on them, payments expected soon and contracts ending soon. 4-6 bullets with the key numbers, '
            .'then one line on what to watch. Under 150 words, no tables, no preamble.';
    }

    protected function emptyText(): string
    {
        return __('اضغط "توليد الرؤى" ليلخّص لك المساعد وضع وحداتك: المستحق لك، المحصّل هذه السنة، الإشغال، والمتأخرات.');
    }
}
