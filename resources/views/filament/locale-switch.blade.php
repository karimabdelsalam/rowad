<div class="text-center text-sm">
    <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="text-primary-600 hover:underline">
        {{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}
    </a>
</div>
