@extends('site.layout')

@section('content')
<div class="hero">
    <div class="container">
        <h1>{{ \App\Support\Website::text('landing_hero_title') ?: __('وحدات متاحة للإيجار') }}</h1>
        <p>{{ \App\Support\Website::text('landing_hero_subtitle') ?: __('تصفح الوحدات المتاحة حاليًا وتواصل معنا مباشرة') }}</p>
    </div>
</div>

<div class="container">
    <form class="search" method="get" action="{{ route('site.home') }}#units">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ابحث باسم الوحدة') }}">
        <select name="city">
            <option value="">{{ __('كل المدن') }}</option>
            @foreach ($cities as $city)
                <option value="{{ $city->id }}" @selected(($filters['city'] ?? null) == $city->id)>{{ $city->name }}</option>
            @endforeach
        </select>
        <select name="category">
            <option value="">{{ __('كل الأنواع') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(($filters['category'] ?? null) == $category->id)>{{ __($category->title) }}</option>
            @endforeach
        </select>
        <input type="number" name="rooms" min="0" value="{{ $filters['rooms'] ?? '' }}" placeholder="{{ __('عدد الغرف (من)') }}">
        <input type="number" name="max_price" min="0" value="{{ $filters['max_price'] ?? '' }}" placeholder="{{ __('أقصى سعر') }}">
        <button class="btn btn-primary" type="submit">{{ __('بحث') }}</button>
    </form>
</div>

<section id="units">
    <div class="container">
        <h2 class="section-title">{{ __('الوحدات المتاحة') }} <small style="color:var(--muted);font-weight:500">({{ $units->total() }})</small></h2>
        @if ($units->isEmpty())
            <div class="empty">{{ $totalListed ? __('لا توجد وحدات مطابقة لبحثك') : __('لا توجد وحدات متاحة حاليًا') }}</div>
        @else
            <div class="grid">
                @foreach ($units as $unit)
                    @include('site.partials.unit-card', ['unit' => $unit])
                @endforeach
            </div>
            @if ($units->hasPages())
                <div class="pagination">
                    @foreach ($units->getUrlRange(1, $units->lastPage()) as $page => $url)
                        <a href="{{ $url }}#units" @class(['active' => $page === $units->currentPage()])>{{ $page }}</a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</section>

@if ($about = \App\Support\Website::text('landing_about'))
<section>
    <div class="container">
        <h2 class="section-title">{{ __('من نحن') }}</h2>
        <p style="white-space:pre-line">{{ $about }}</p>
    </div>
</section>
@endif

<section id="contact">
    <div class="container" style="max-width:640px">
        <h2 class="section-title">{{ __('تواصل معنا') }}</h2>
        @include('site.partials.inquiry-form', ['action' => route('site.contact')])
    </div>
</section>
@endsection
