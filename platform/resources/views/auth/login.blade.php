<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دخول الكونسول</title>
<link rel="stylesheet" href="{{ asset('assets/console.css') }}">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1 class="auth-title">💼 {{ settings('brand_name', 'Planova بلانوفا') }}</h1>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if ($awaitingCode)
        <p class="muted">افتح تطبيق المصادقة وأدخل الرمز المكوَّن من 6 أرقام.</p>
        <form method="post" action="{{ route('login.totp') }}">@csrf
            <label>رمز التحقق
                <input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                       autocomplete="one-time-code" autofocus dir="ltr"
                       style="text-align:center;font-size:24px;letter-spacing:8px"></label>
            <button class="btn btn-block" type="submit">تأكيد</button>
        </form>
    @else
        <form method="post" action="{{ route('login.password') }}">@csrf
            <label>اسم المستخدم
                <input name="username" value="{{ old('username') }}" required autofocus dir="ltr"></label>
            <label>كلمة المرور <input type="password" name="password" required dir="ltr"></label>
            <button class="btn btn-block" type="submit">دخول</button>
        </form>
    @endif
</div>
</body>
</html>
