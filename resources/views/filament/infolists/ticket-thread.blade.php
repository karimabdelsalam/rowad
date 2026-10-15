@php
    $ticket = $getRecord();
    $sender = \App\Filament\Admin\Resources\SupportTickets\Schemas\SupportTicketInfolist::senderName($ticket) ?? __('مقدّم الطلب');
    $replies = $ticket->replies()->with('user')->oldest()->get();
@endphp
<div class="rowad-thread">
    <div class="rowad-thread__msg is-them">
        <div class="rowad-thread__meta"><b>{{ $sender }}</b><span>{{ $ticket->created_at?->format('Y-m-d H:i') }}</span></div>
        <div class="rowad-thread__body">{!! nl2br(e($ticket->message)) !!}</div>
    </div>
    @foreach ($replies as $reply)
        @php $mine = $reply->user === null || $reply->user->type?->value === 'staff' || $reply->user->is_super_admin; @endphp
        <div class="rowad-thread__msg {{ $mine ? 'is-me' : 'is-them' }}">
            <div class="rowad-thread__meta"><b>{{ $reply->user?->name ?? __('المكتب') }}</b><span>{{ $reply->created_at?->format('Y-m-d H:i') }}</span></div>
            <div class="rowad-thread__body">{!! nl2br(e($reply->message)) !!}</div>
        </div>
    @endforeach
    @if ($replies->isEmpty())
        <p class="rowad-thread__empty">{{ __('لم يُرَدّ على هذه التذكرة بعد — استخدم زر «رد» في أعلى الصفحة.') }}</p>
    @endif
</div>
