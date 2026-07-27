<form action="{$CPURL}/{$cumodule.name}/{$cuaction.name}" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <input type="hidden" name="buildid" value="{$data.buildid|default:$smarty.get.buildid}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"مسمى التقرير"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="title" value="{$data.title|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2">{"النوع"|gettext}</label>
            <div class="col-md-2">
              <select name="catid" class="form-control" required>
                  {foreach $categories as $category}
                    <option value="{$category.id}" {if $category.id eq $data.catid}selected="selected"{/if}>{$category.name}</option>
                  {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"التاريخ"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control datepicker" name="issued_date" value="{$data.issued_date|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"الملف"|gettext}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="file" type="file" />
                {if !empty($data.file)}
                  <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$data.file|clean}" target="_blank"><i class="fa fa-eye"></i></a></span>
                {/if}
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
