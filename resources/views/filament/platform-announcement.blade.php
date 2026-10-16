<div class="rowad-plan-notice rowad-plan-notice--{{ $level }}" role="status" style="margin-top:0">
    <x-filament::icon :icon="$level === 'danger' ? 'heroicon-o-exclamation-triangle' : ($level === 'warning' ? 'heroicon-o-bell-alert' : 'heroicon-o-megaphone')" class="rowad-plan-notice__icon" />
    <span>{{ $text }}</span>
</div>
