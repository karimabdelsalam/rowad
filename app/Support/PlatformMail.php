<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;

/**
 * Puts the mail server saved in the platform console in place of the MAIL_* values from .env, so the
 * platform's own emails (signup, welcome, alerts, password resets) and every office without its own SMTP
 * go out through it. Nothing saved: .env stays in charge.
 */
final class PlatformMail
{
    public static function apply(): void
    {
        PlatformSettings::flush();
        try {
            $all = PlatformSettings::all();
        } catch (\Throwable) {
            return; // central database not ready yet (fresh install, migrations pending)
        } finally {
            PlatformSettings::flush(); // read fresh again later in the request
        }
        $mailer = $all['mail_mailer'] ?? null;
        $config = [];

        if ($mailer === 'smtp' && filled($all['mail_host'] ?? null)) {
            $port = (int) (($all['mail_port'] ?? null) ?: 587);
            $encryption = $all['mail_encryption'] ?? 'tls';
            $config += [
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $all['mail_host'],
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.scheme' => $encryption === 'ssl' || $port === 465 ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => ($all['mail_username'] ?? null) ?: null,
                'mail.mailers.smtp.password' => PlatformSettings::secret('mail_password'),
            ];
        } elseif (in_array($mailer, ['sendmail', 'log'], true)) {
            $config['mail.default'] = $mailer;
        }
        if (filled($all['mail_from_address'] ?? null)) {
            $config['mail.from.address'] = $all['mail_from_address'];
        }
        if (filled($all['mail_from_name'] ?? null)) {
            $config['mail.from.name'] = $all['mail_from_name'];
        }
        if (filled($all['mail_reply_to'] ?? null)) {
            $config['mail.reply_to'] = ['address' => $all['mail_reply_to'], 'name' => $config['mail.from.name'] ?? config('mail.from.name')];
        }
        if ($config === []) {
            return;
        }
        config($config);
        foreach (['smtp', 'sendmail', 'log'] as $name) {
            Mail::purge($name); // drop a mailer already built with the old settings
        }
    }
}
