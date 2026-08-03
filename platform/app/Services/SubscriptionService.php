<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * منطق الاشتراكات والفوترة.
 *
 * هذه أخطر وحدة في النظام: خطأ فيها يعني اشتراكًا يُمدّ مرتين أو عميلًا يدفع
 * ولا يُفعَّل. لذلك هي أول ما نُقل إلى لارافيل، ومغطاة باختبارات تصف السلوك
 * المتوقَّع بدل الاعتماد على التجربة اليدوية.
 */
class SubscriptionService
{
    /**
     * يعيد حساب حالة الفاتورة من مدفوعاتها المؤكدة، ويمدّ الاشتراك مرة واحدة
     * فقط عند اكتمال السداد.
     *
     * المدّ محكوم بعلم `applied`: إعادة تأكيد دفعة لا تمدّ الاشتراك ثانيةً،
     * وإرجاع الفاتورة لغير مكتملة (بحذف دفعة أو إلغاء تأكيدها) يعكس المدّ بنفس
     * عدد الشهور.
     */
    public function recalculate(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $inv = Invoice::lockForUpdate()->find($invoice->id);
            if (!$inv || $inv->status === 'void') {
                return $invoice;
            }

            $paid = (float)$inv->payments()->confirmed()->sum('amount');
            $amount = (float)$inv->amount;

            // فرق أقل من قرش يُعتبر سدادًا كاملًا
            $isPaid = $paid + 0.005 >= $amount;
            $status = $isPaid ? 'paid' : ($paid > 0.005 ? 'partial' : 'unpaid');

            $applied = (bool)$inv->applied;
            $months  = max(0, (int)$inv->months);

            if ($isPaid && !$applied && $months > 0) {
                $this->extend($inv->clinic, $months);
                $applied = true;
            } elseif (!$isPaid && $applied && $months > 0) {
                $this->extend($inv->clinic, -$months);
                $applied = false;
            }

            $inv->forceFill([
                'status'  => $status,
                'paid'    => round($paid, 2),
                'applied' => $applied,
            ])->save();

            return $inv;
        });
    }

    /**
     * يمدّ اشتراك العيادة بعدد شهور (سالب = تراجع).
     *
     * المدّ يبدأ من تاريخ الانتهاء الحالي إن كان الاشتراك ساريًا، ومن اليوم إن
     * كان منتهيًا — فلا يضيع على العميل ما تبقّى له، ولا يُحتسب له تجديد رجعي
     * عن فترة انقطاع.
     */
    public function extend(Clinic $clinic, int $months): Clinic
    {
        $fresh = Clinic::lockForUpdate()->find($clinic->id);
        if (!$fresh) {
            return $clinic;
        }

        $today = now()->startOfDay();
        $base = ($fresh->expires_at && $fresh->expires_at->gt($today))
            ? $fresh->expires_at->copy()
            : $today->copy();

        $fresh->expires_at = $months >= 0
            ? $base->addMonths($months)
            : $base->subMonths(abs($months));

        // السداد يعيد تفعيل عيادة تجريبية أو موقوفة تلقائيًا
        if ($months > 0 && in_array($fresh->status, ['trial', 'suspended'], true)) {
            $fresh->status = 'active';
        }
        $fresh->save();

        // نُبقي الكائن المُمرَّر متسقًا مع ما حُفظ
        $clinic->setRawAttributes($fresh->getAttributes(), true);

        return $fresh;
    }

    /** رقم فاتورة تسلسلي بصيغة INV-YYYY-0001 */
    public function nextInvoiceNumber(): string
    {
        $year = now()->format('Y');
        $last = Invoice::where('number', 'like', "INV-$year-%")
            ->orderByDesc('id')
            ->value('number');

        $seq = $last ? ((int)substr($last, -4)) + 1 : 1;

        return sprintf('INV-%s-%04d', $year, $seq);
    }

    /** الدخل الشهري المتكرر من العيادات المشتركة */
    public function monthlyRecurringRevenue(): float
    {
        return (float)Clinic::where('clinics.status', 'active')
            ->join('plans', 'plans.id', '=', 'clinics.plan_id')
            ->selectRaw('COALESCE(SUM(plans.price / GREATEST(plans.months, 1)), 0) AS mrr')
            ->value('mrr');
    }
}
