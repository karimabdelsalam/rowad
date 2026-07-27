<form action="{$CPURL}/{$cumodule.name}/{$cuaction.name}" class="form-horizontal margin-none form-ajax" method="post">
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"أسم الفئة"|gettext}</label>
          </div>
          {section name=op loop=20}
            <div class="form-group">
              <div class="col-md-4">
                <input class="form-control" name="title[]" type="text" />
              </div>
            </div>
          {/section}
        </div>
      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
