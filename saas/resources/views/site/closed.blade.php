<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.favicons')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ \App\Support\Website::name() }}</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700,800&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Cairo', system-ui, sans-serif; background: #f0fdfa; color: #134e4a; text-align: center; padding: 16px; }
        main { max-width: 560px; }
        h1 { font-size: 1.6rem; margin: 0 0 .75rem; }
        p { font-size: 1.1rem; line-height: 1.9; white-space: pre-line; }
    </style>
</head>
<body>
<main>
    <h1>{{ \App\Support\Website::name() }}</h1>
    <p>{{ $message }}</p>
</main>
</body>
</html>
