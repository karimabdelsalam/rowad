<?php

namespace App\Filament\Owner\Pages;

use App\Filament\Pages\AssistantPage;

class Assistant extends AssistantPage
{
    protected function suggestions(): array
    {
        return [
            'كم رصيدي المستحق الآن؟',
            'هل يوجد إيجار متأخر على وحداتي؟',
            'ما الدفعات المتوقعة خلال الشهرين القادمين؟',
            'لخص لي كشف حسابي الأخير',
        ];
    }

    protected function intro(): string
    {
        return 'اسأل عن وحداتك وإيجاراتها والمبالغ المستحقة لك وكشف حسابك، وأجيبك بالأرقام.';
    }

    protected function example(): string
    {
        return 'اكتب سؤالك… مثال: كم استلمت هذه السنة؟';
    }
}
