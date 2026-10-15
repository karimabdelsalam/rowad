<?php

use App\Models\MessageTemplate;
use Database\Seeders\Tenant\TemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/** New email bodies with a details box; templates the organisation already edited are left alone. */
return new class extends Migration
{
    /** The previous default bodies, to recognise templates nobody changed. */
    private const PREVIOUS = [
            'rent_due_15' => '<p>عزيزي {name}،</p><p>نذكركم باستحقاق قسط إيجار الوحدة {unit} بمبلغ {amount} بتاريخ {due_date}.</p>',
            'sale_due_15' => '<p>عزيزي {name}،</p><p>يستحق قسط عقد الشراء للوحدة {unit} بمبلغ {amount} بتاريخ {due_date}.</p>',
            'sale_due_1' => '<p>عزيزي {name}، يستحق غدًا قسط الوحدة {unit} بمبلغ {amount}.</p>',
            'sale_due_0' => '<p>عزيزي {name}، يستحق اليوم قسط الوحدة {unit} بمبلغ {amount}.</p>',
            'sale_overdue' => '<p>عزيزي {name}، نفيدكم بتأخر سداد قسط الوحدة {unit} بمبلغ {amount} المستحق بتاريخ {due_date}.</p>',
            'sale_collected' => '<p>عزيزي {name}، تم استلام مبلغ {amount} عن الوحدة {unit}. شكرًا لكم.</p>',
            'owner_unit_rented' => '<p>عزيزي {name}، تم تأجير الوحدة {unit} إلى {renters} من {start_date} إلى {end_date} بقيمة {amount}.</p>',
            'owner_payout' => '<p>عزيزي {name}، تم تحويل مبلغ {amount} لحسابكم بتاريخ {date} بموجب السند رقم {voucher_number}.</p>',
            'new_rent_contract' => '<p>عزيزي {name}، تم تسجيل عقد إيجار الوحدة {unit} رقم {contract_number} من {start_date} إلى {end_date}.</p>',
            'contract_ended_renter' => '<p>عزيزي {name}، ينتهي عقد إيجار الوحدة {unit} بتاريخ {end_date}.</p>',
    ];

    public function up(): void
    {
        $defaults = (new TemplateSeeder)->messages();
        foreach (self::PREVIOUS as $event => $previous) {
            MessageTemplate::where('event', $event)->where('email_body', $previous)->update(['email_body' => $defaults[$event][1]]);
        }
    }

    public function down(): void
    {
        // the old bodies are plain sentences; nothing to restore
    }
};
