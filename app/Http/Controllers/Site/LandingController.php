<?php

namespace App\Http\Controllers\Site;

use App\Exceptions\BusinessRuleException;
use App\Models\City;
use App\Services\Viewings\ViewingSchedule;
use App\Models\InboxMessage;
use App\Models\Lookups\PropertyCategory;
use App\Models\Unit;
use App\Models\User;
use App\Support\Website;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Public site on each office's domain, fed live from the office's units. */
class LandingController
{
    public function home(Request $request): View|RedirectResponse
    {
        if (! Website::enabled()) {
            // legacy site_closed message, shown instead of the site while it is closed
            return filled($message = \App\Support\Settings::get('site_closed_message'))
                ? view('site.closed', ['message' => $message])
                : redirect('/admin');
        }

        $filters = $request->validate([
            'city' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'rooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $units = Unit::listed()
            ->with(['city', 'district', 'category', 'media'])
            ->when($filters['city'] ?? null, fn ($q, $v) => $q->where('city_id', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('property_category_id', $v))
            ->when($filters['rooms'] ?? null, fn ($q, $v) => $q->where('rooms', '>=', $v))
            ->when($filters['max_price'] ?? null, fn ($q, $v) => $q->where('rent_amount', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('title', 'like', "%{$v}%")->orWhere('title_en', 'like', "%{$v}%")))
            ->orderByDesc('is_featured')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('site.home', [
            'units' => $units,
            'filters' => $filters,
            'cities' => City::whereHas('units', fn ($q) => $q->listed())->orderBy('name')->get(),
            'categories' => PropertyCategory::whereHas('units', fn ($q) => $q->listed())->orderBy('title')->get(),
            'totalListed' => Unit::listed()->count(),
        ]);
    }

    public function show(Unit $unit): View
    {
        abort_unless(Website::enabled() && Unit::listed()->whereKey($unit->getKey())->exists(), 404);

        $unit->load(['city', 'district', 'category', 'building', 'media']);

        return view('site.unit', [
            'unit' => $unit,
            'slots' => ViewingSchedule::enabled() ? app(ViewingSchedule::class)->slots($unit) : [],
            'similar' => Unit::listed()->with(['city', 'district', 'category', 'media'])->whereKeyNot($unit->getKey())
                ->where('property_category_id', $unit->property_category_id)->limit(3)->get(),
        ]);
    }

    /** A visitor books a viewing slot for a listed unit. */
    public function bookViewing(Request $request, Unit $unit, ViewingSchedule $viewings): RedirectResponse
    {
        abort_unless(Website::enabled() && ViewingSchedule::enabled() && Unit::listed()->whereKey($unit->getKey())->exists(), 404);
        $data = $request->validate([
            'slot' => ['required', 'date_format:Y-m-d H:i'],
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s-]{7,20}$/'],
            'website' => ['prohibited'], // honeypot
        ], [], ['slot' => __('الموعد'), 'name' => __('الاسم'), 'mobile' => __('الجوال')]);

        try {
            $viewing = $viewings->book($unit, ['starts_at' => $data['slot'], 'name' => $data['name'], 'mobile' => $data['mobile']], 'website', $request->ip());
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['slot' => $e->getMessage()])->withInput();
        }

        return back()->with('viewing_booked', $viewing->starts_at->format('Y-m-d H:i'));
    }

    public function inquire(Request $request, ?Unit $unit = null): RedirectResponse
    {
        abort_unless(Website::enabled(), 404);
        if ($unit !== null) {
            abort_unless(Unit::listed()->whereKey($unit->getKey())->exists(), 404);
        }

        $key = 'inquiry:'.tenant('id').':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['message' => __('عدد كبير من الطلبات، حاول لاحقًا')])->withInput();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['required_without:email', 'nullable', 'string', 'max:30', 'regex:/^[0-9+\s-]{7,20}$/'],
            'email' => ['required_without:mobile', 'nullable', 'email', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'website' => ['prohibited'], // honeypot
        ]);
        RateLimiter::hit($key, 3600);

        $message = InboxMessage::create([
            'source' => $unit ? 'unit_inquiry' : 'contact',
            'unit_id' => $unit?->id,
            'name' => $data['name'],
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'subject' => $unit ? __('استفسار عن :unit', ['unit' => $unit->title]) : __('رسالة من الموقع'),
            'message' => $data['message'],
            'locale' => app()->getLocale(),
            'ip_address' => $request->ip(),
        ]);

        $recipients = User::permission('inbox_messages.view_any')->where('is_active', true)->get();
        if ($recipients->isNotEmpty()) {
            Notification::make()
                ->title(__('استفسار جديد من الموقع'))
                ->body("{$message->name}: ".str($message->message)->limit(80))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->actions([Action::make('open')->label(__('عرض'))->url('/admin/inbox-messages/'.$message->id)])
                ->sendToDatabase($recipients);
        }

        return back()->with('inquiry_sent', true);
    }
}
