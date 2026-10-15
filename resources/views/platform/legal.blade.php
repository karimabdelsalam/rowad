@extends('platform.layout')

@section('title', $title.' — '.__('عقار نوفا'))

@section('styles')
    .legal { padding: 56px 0 88px; background: var(--soft); }
    .legal article { max-width: 820px; margin: 0 auto; background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 40px 44px; box-shadow: var(--shadow); }
    .legal h1 { font-size: 2rem; font-weight: 800; margin-bottom: 6px; }
    .legal .updated { color: var(--muted); margin: 0 0 28px; font-size: .92rem; }
    .legal h2 { font-size: 1.15rem; font-weight: 800; margin: 28px 0 8px; }
    .legal p, .legal li { color: var(--body); line-height: 1.95; }
    .legal ul { padding-inline-start: 22px; margin: 0 0 10px; }
    .legal a { color: var(--brand); font-weight: 700; }
    @media (max-width: 640px) { .legal article { padding: 26px 20px; } }
@endsection

@section('content')
    <section class="legal">
        <div class="container">
            <article>
                <h1>{{ $title }}</h1>
                <p class="updated">{{ __('آخر تحديث: :date', ['date' => '2026-10-16']) }}</p>
                @include('platform.legal.'.$page.'-'.(app()->getLocale() === 'en' ? 'en' : 'ar'))
            </article>
        </div>
    </section>
@endsection
