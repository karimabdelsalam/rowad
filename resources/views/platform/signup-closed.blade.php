@extends('platform.layout', ['title' => __('التسجيل مغلق')])

@section('content')
<section class="hero" style="min-height:50vh">
    <div class="container" style="max-width:640px;text-align:center">
        <h1 style="margin-bottom:12px">{{ __('التسجيل مغلق حاليًا') }}</h1>
        <p style="color:#64748b;font-size:1.05rem">{{ filled($message) ? $message : __('نستقبل مؤسسات جديدة قريبًا. تواصل معنا وسنبلغك فور فتح التسجيل.') }}</p>
        <p style="margin-top:24px"><a class="btn btn-primary" href="{{ route('central.home', [], false) }}">{{ __('العودة للصفحة الرئيسية') }}</a></p>
    </div>
</section>
@endsection
