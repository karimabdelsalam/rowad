<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/** سجل نشاط الكونسول — يُكتب مباشرةً فلا يضيع لو فشلت المعاملة المحيطة به */
class ActivityLogger
{
    public function record(string $action, string $entity, ?int $entityId, string $summary): void
    {
        DB::table('console_log')->insert([
            'user_id'    => Auth::id(),
            'action'     => $action,
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'summary'    => mb_substr($summary, 0, 255),
            'ip'         => substr((string)Request::ip(), 0, 45),
            'created_at' => now(),
        ]);
    }
}
