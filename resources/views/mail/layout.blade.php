@php
    $rtl = $locale !== 'en';
    $brand = '#0f766e';
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;font-family:Cairo,Tahoma,Arial,sans-serif;direction:{{ $rtl ? 'rtl' : 'ltr' }};text-align:{{ $rtl ? 'right' : 'left' }};">
            <tr><td style="height:6px;background:{{ $brand }};background:linear-gradient(90deg,#0f766e,#14b8a6);font-size:0;line-height:0;">&nbsp;</td></tr>
            <tr><td style="padding:24px 28px 8px;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $org }}" height="44" style="height:44px;width:auto;display:block;border:0;">
                @else
                    <div style="font-size:20px;font-weight:800;color:{{ $brand }};">{{ $org }}</div>
                @endif
            </td></tr>
            @if (filled($subject))
                <tr><td style="padding:12px 28px 0;font-size:20px;font-weight:800;color:#0f172a;line-height:1.5;">{{ $subject }}</td></tr>
            @endif
            <tr><td style="padding:12px 28px 24px;font-size:15px;line-height:1.9;color:#334155;">{!! $body !!}</td></tr>
            <tr><td style="padding:18px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.8;color:#64748b;">
                <strong style="color:#0f172a;">{{ $org }}</strong>
                @foreach (array_filter($contact) as $line)
                    <br>{{ $line }}
                @endforeach
                <br><span style="color:#94a3b8;">{{ $rtl ? 'هذه رسالة آلية، يمكنك الرد عليها للتواصل معنا.' : 'This is an automated message; reply to reach us.' }}</span>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
