<form action="{$CPURL}/home/attach" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="tempid" value="{$smarty.get.tempid}" />
  <div class="row innerLR">
    <div class="form-group">
      <label class="col-md-4 control-label">{"مسمي الملف"|gettext}</label>
      <div class="col-md-5">
        <input class="form-control" name="title" type="text" required />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{"إختر الملف من جهازك"|gettext}</label>
      <div class="col-md-6">
        <input class="form-control" name="file" type="file" required />
      </div>
    </div>
  </div>
  <div class="submit-block">
    <button type="submit" class="btn btn-success"><i class="fa fa-check-circle"></i> {"رفع الملف"|gettext}</button>
    <button type="button" class="btn btn-default" onclick="$('#open-ajax-form').modal('hide')"><i class="fa fa-close"></i> {"إلغاء"|gettext}</button>
  </div>
</form>
