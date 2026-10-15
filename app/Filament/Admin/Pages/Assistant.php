<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Pages\AssistantPage;

class Assistant extends AssistantPage
{
    protected function suggestions(): array
    {
        return [
            'أعطني ملخصًا لأداء المؤسسة هذا الشهر مع توصيات',
            'من المستأجرون المتأخرون في السداد وكم المبالغ؟',
            'ما العقود التي تنتهي خلال 60 يومًا؟',
            'قارن التحصيل الشهري هذه السنة وحدد أضعف الأشهر',
            'ما المباني الأعلى إشغالًا والأقل؟',
            'كم الوحدات المتاحة للإيجار الآن؟',
        ];
    }
}
