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
            <label class="col-md-2 control-label">{"مسمي النوع"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="title" value="{$data.title|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"صفته"|gettext}</label>
            <div class="col-md-3">
              <select name="attrs" class="form-control" required>
                <option value="LW" {if $data.attrs eq "LW"}selected="selected"{/if}>{"يمتلك وحدة قياس طول وعرض"|gettext}</option>
                <option value="NL" {if $data.attrs eq "NL"}selected="selected"{/if}>{"يمتلك طول وعرض"|gettext}</option>
                <option value="L" {if $data.attrs eq "L"}selected="selected"{/if}>{"يمتلك وحدة قياس طول فقط"|gettext}</option>
              </select>
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
