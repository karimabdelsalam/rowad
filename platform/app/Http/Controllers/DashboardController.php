<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\SubscriptionService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** أيام التنبيه قبل انتهاء الاشتراك */
    private const WARN_DAYS = 14;

    public function __construct(private SubscriptionService $subs)
    {
    }

    public function __invoke(): View
    {
        return view('console.dashboard', [
            'mrr'            => $this->subs->monthlyRecurringRevenue(),
            'monthCollected' => (float)Payment::confirmed()
                ->where('pdate', '>=', now()->startOfMonth())->sum('amount'),
            'outstanding'    => (float)Invoice::whereIn('status', ['unpaid', 'partial'])
                ->selectRaw('COALESCE(SUM(amount - paid), 0) AS due')->value('due'),
            'pendingPays'    => Payment::where('status', 'pending')->count(),
            'byStatus'       => Clinic::selectRaw('status, COUNT(*) AS n')
                ->groupBy('status')->pluck('n', 'status')->all(),
            'totalClinics'   => Clinic::count(),

            // اشتراكات تحتاج تجديدًا: قاربت على الانتهاء أو انتهت بالفعل
            'expiring' => Clinic::with('plan')->live()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(self::WARN_DAYS))
                ->orderBy('expires_at')->get(),

            'overdue' => Invoice::with('clinic')
                ->whereIn('status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')->whereDate('due_date', '<', now())
                ->orderBy('due_date')->limit(15)->get(),

            'recentPays' => Payment::with(['clinic', 'invoice'])
                ->latest('id')->limit(10)->get(),

            // نسخ مشتركة لم تتصل منذ ثلاثة أيام: إما متوقفة أو تغيّر رابطها
            'silent' => Clinic::where('status', 'active')
                ->where(fn ($q) => $q->whereNull('last_ping_at')
                    ->orWhere('last_ping_at', '<', now()->subDays(3)))
                ->orderByRaw('last_ping_at IS NOT NULL, last_ping_at')
                ->limit(10)->get(),

            'warnDays' => self::WARN_DAYS,
        ]);
    }
}
