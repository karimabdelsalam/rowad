<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-4 control-label">{"مسمي النوع"|gettext}</label>
            <label class="col-md-4 control-label">{"صفته"|gettext}</label>
          </div>
          {section name=op loop=20}
            <div class="form-group">
              <div class="col-md-4">
                <input class="form-control" name="title[]" type="text" />
              </div>
              <div class="col-md-4">
                <select name="attrs[]" class="form-control">
                  <option value="">{"اختر صفته"|gettext}</option>
                  <option value="LW">{"يمتلك وحدة قياس طول و عرض"|gettext}</option>
                  <option value="NL">{"يمتلك طول ورقم"|gettext}</option>
                  <option value="L">{"يمتلك وحدة قياس طول فقط"|gettext}</option>
                </select>
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
