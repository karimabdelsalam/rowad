<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="number" value="{$smarty.get.number}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"رقم الجوال"|gettext}</label>
            <div class="col-md-3">
              <label class="label label-primary">{$smarty.get.number|clean}</label>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"نص رسالة SMS"|gettext}</label>
            <div class="col-md-5">
              <textarea class="form-control" id="smsCounter" name="sms" rows="6">{$data.sms|clean}</textarea>
            </div>
            <div class="col-md-2">
              <ul class="list-unstyled" id="smsCounterStats">
                <li class="label label-primary marginTB">{"عدد الرسائل"|gettext}<span class="label label-default messages"></span></li>
                <li class="label label-danger marginTB">{"مسموح بعدد"|gettext} <span class="label label-default per_message"></span></li>
                <li class="label label-success marginTB">{"المتبقي"|gettext} <span class="label label-default remaining"></span></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div class="separator"></div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ التغييرات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
