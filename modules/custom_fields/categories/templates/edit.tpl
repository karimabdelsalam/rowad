<form action="{$CPURL}/{$cumodule.name}/{$cuaction.name}" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم القسم"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="title" value="{$data.title|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"فئة العقار"|gettext}</label>
            <div class="col-md-3">
              <select name="build_catid" class="ajaxSelect" required style="height: 34px;width:80%">
                <option value="">{"اختر فئة"|gettext}</option>
                  {foreach $buildcats as $buildcat}
                    <option value="{$buildcat.id}" {if $data.build_catid eq $buildcat.id}selected="selected"{/if}>{$buildcat.title|clean}</option>
                  {/foreach}
              </select>
              <a href="{$CPURL}/buildcats/add" class="btn btn-default pull-left quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
        </div>
      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
