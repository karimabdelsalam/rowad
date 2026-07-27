<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$smarty.get.id}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"العنوان"|gettext}</label>
            <div class="col-md-6">
              <p class="marginTB">{$data.subject|gettext|clean}</p>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"قالب SMS"|gettext}</label>
            <div class="col-md-5">
              <textarea class="form-control" id="smsCounter" name="sms" rows="6">{$data.sms|clean}</textarea>
            </div>
            <div class="col-md-2">
              <ul class="list-unstyled" id="smsCounterStats">
                <li class="label label-primary marginTB">{"عدد الرسائل"|gettext} <span class="label label-default messages"></span></li>
                <li class="label label-danger marginTB">{"مسموح بعدد"|gettext} <span class="label label-default per_message"></span></li>
                <li class="label label-success marginTB">{"المتبقى"|gettext} <span class="label label-default remaining"></span></li>
              </ul>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"تفعيل SMS"|gettext}</label>
            <div class="col-md-6">
              <input name="sms_active" type="checkbox" class="ckeckonoff" value="{$data.sms_active}" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"القالب البريدي"|gettext}</label>
            <div class="col-md-10">
              <textarea class="form-control ajaxeditor" name="content" rows="8">{$data.content|clean}</textarea>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"تفعيل الإرسال البريدي"|gettext}</label>
            <div class="col-md-6">
              <input name="active" type="checkbox" class="ckeckonoff" value="{$data.active}" />
            </div>
          </div>
        </div>
      </div>
      <div class="separator"></div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ التغييرات"|gettext}</button>
        <button type="reset" class="btn btn-default"><i class="fa fa-times"></i> {"استرجاع"|gettext}</button>
      </div>
    </div>
  </div>
</form>
