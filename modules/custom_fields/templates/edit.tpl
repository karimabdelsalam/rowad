<form action="{$CPURL}/{$cumodule.name}/{$cuaction.name}" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          {if empty($data.id)}
          <div class="form-group">
            <label class="col-md-2 control-label">{"القسم"|gettext}</label>
            <div class="col-md-3">
              <select name="catid" class="ajaxSelect" required style="width: 100%">
                <option value="">{"اختر القسم"|gettext}</option>
                  {foreach $categories as $category}
                    <option value="{$category.id}" {if $data.catid eq $category.id}selected="selected"{/if}>{$category.title|clean}</option>
                  {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"النوع"|gettext}</label>
            <div class="col-md-2">
              <select name="type" class="form-control" required>
                <option value="">{"نوع الحقل"|gettext}</option>
                <option value="checkbox" {if $data.type eq "checkbox"}selected="selected"{/if}>{"مربع اختياري"|gettext}</option>
                <option value="text" {if $data.type eq "text"}selected="selected"{/if}>{"نصية"|gettext}</option>
              </select>
            </div>
          </div>
          {/if}
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم الحقل"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control" name="title" value="{$data.title|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"الوصف"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="description" value="{$data.description|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"النص الملحق"|gettext}</label>
            <div class="col-md-2">
              <input class="form-control" name="suffix" value="{$data.suffix|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"الرمز"|gettext}</label>
            <div class="col-md-3">
              <select name="icon" class="js-select-templating noSelect2" style="width: 260px">
                  <option value="">{"بدون رمز"|gettext}</option>
                {foreach $fontIcons as $fontIcon}
                  <option>{$fontIcon}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"حقل الزامي"|gettext}</label>
            <div class="col-md-5">
              <input name="required" type="checkbox" class="ckeckonoff" value="{$data.required}" />
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
