<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/** The "تواصل معنا" form at the foot of the official site: kept for the platform console and mailed to the team. */
class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $back = redirect()->to(route('central.home', [], false).'#contact');

        // a hidden field people never see; bots fill it in
        if (filled($request->input('website'))) {
            return $back->with('contact_sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:30', 'regex:/^[0-9+\s\-()]{7,30}$/'],
            'email' => ['required_without:phone', 'nullable', 'email:rfc', 'max:190'],
            'city' => ['nullable', 'string', 'max:80'],
            'topic' => ['required', Rule::in(array_keys(ContactMessage::TOPICS))],
            'message' => ['required', 'string', 'min:5', 'max:3000'],
        ], [], [
            'name' => __('الاسم'), 'phone' => __('الجوال'), 'email' => __('البريد الإلكتروني'), 'message' => __('الرسالة'), 'topic' => __('الموضوع'),
        ]);

        $contact = ContactMessage::create($data + ['locale' => app()->getLocale(), 'ip' => $request->ip()]);

        try {
            if ($to = PlatformSettings::signupAlertEmails()) {
                Mail::to($to)->queue(new ContactMessageReceived($contact));
            }
        } catch (\Throwable $e) {
            Log::warning('Contact message mail not sent', ['contact' => $contact->id, 'error' => $e->getMessage()]);
        }

        return $back->with('contact_sent', true);
    }
}
