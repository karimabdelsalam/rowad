<iframe src="{{ \App\Support\Maps::embedUrl($getRecord()->latitude, $getRecord()->longitude) }}" title="{{ __('الموقع') }}" loading="lazy"
    style="width:100%;height:280px;border:0;border-radius:12px;background:#f1f5f9"></iframe>
