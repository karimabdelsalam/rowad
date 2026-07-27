<div class="widget">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <div class="widget-body innerAll">
    <div class="row innerLR">
      <div class="col-md-12 text-center">
        <h4 class="innerAll bg-container margin-none">{"كود الوصول الى لوحة تحكم المالك"|gettext}</h4>
        <div class="separator"></div>
        <p><img class="logo" src="{$MEDIAURL}/{$config['logo']}" alt="Logo" width="200"></p>
        <h1 class="mt-15">{$config['sitename']}</h1>
        <h3 class="mt-30">{"امسح الكود بإستخدام كاميرا جوالك للوصول الى تطبيق إدارة العقار"|gettext}</h3>
        <p class="mt-30 mt-30"><img src="{$owner_code}" /></p>
        <a href="?print=owner" target="_blank" class="btn btn-primary mt-40"><i class="fa fa-print"></i> {"طباعة"|gettext}</a>
      </div>
    </div>
    <div class="row innerLR mt-40">
      <div class="col-md-12 text-center">
        <h4 class="innerAll bg-container margin-none">{"كود الوصول الى لوحة تحكم المستأجر"|gettext}</h4>
        <div class="separator"></div>
        <p><img class="logo" src="{$MEDIAURL}/{$config['logo']}" alt="Logo" width="200"></p>
        <h1 class="mt-15">{$config['sitename']}</h1>
        <h3 class="mt-30">{"امسح الكود بإستخدام كاميرا جوالك للوصول الى تطبيق إدارة العقار"|gettext}</h3>
        <p class="mt-30 mt-30"><img src="{$client_code}" /></p>
        <a href="?print=client" target="_blank" class="btn btn-primary mt-40"><i class="fa fa-print"></i> {"طباعة"|gettext}</a>
      </div>
    </div>
    <div class="row innerLR mt-40">
      <div class="col-md-12 text-center">
        <h4 class="innerAll bg-container margin-none">{"كود الوصول الى الواجهة التسويقية"|gettext}</h4>
        <div class="separator"></div>
        <p><img class="logo" src="{$MEDIAURL}/{$config['logo']}" alt="Logo" width="200"></p>
        <h1 class="mt-15">{$config['sitename']}</h1>
        <h3 class="mt-30">{"امسح الكود بإستخدام كاميرا جوالك للوصول الى موقعنا الإلكتروني"|gettext}</h3>
        <p class="mt-30 mt-30"><img src="{$frontend_code}" /></p>
        <a href="?print=sales" target="_blank" class="btn btn-primary mt-40"><i class="fa fa-print"></i> {"طباعة"|gettext}</a>
      </div>
    </div>
  </div>
</div>
