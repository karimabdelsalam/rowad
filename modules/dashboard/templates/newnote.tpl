<form action="{$CPURL}/{$cumodule.name|clean}/index/?do=newnote" class="form-horizontal form-ajax" role="form" method="post">
  <div class="innerAll inner-2x">
      <textarea name="content" class="notebook border-none form-control padding-none" rows="4" required placeholder="{"اكتب ملاحظتك هنا..."|gettext}"></textarea>
      <div class="clearfix"></div>
  </div>
  <div class="innerAll border-top">
      <button type="submit" class="btn btn-primary"><i class="fa fa-fw fa-check"></i> {"حفظ الآن"|gettext}</button>
      <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-fw fa-times"></i> {"إلغاء"|gettext}</button>
  </div>
</form>