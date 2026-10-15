<?php

namespace App\Filament\Client\Pages;

use App\Filament\Pages\AssistantPage;

class Assistant extends AssistantPage
{
    protected function suggestions(): array
    {
        return [
            'متى دفعتي القادمة وكم مبلغها؟',
            'هل علي مبالغ متأخرة؟',
            'متى ينتهي عقدي؟',
            'ما حالة طلبات الدعم التي أرسلتها؟',
        ];
    }

    protected function intro(): string
    {
        return 'اسأل عن عقدك ودفعاتك ومواعيدها وطلبات الدعم، وأجيبك فورًا.';
    }

    protected function example(): string
    {
        return 'اكتب سؤالك… مثال: كم المتبقي علي هذه السنة؟';
    }
}
