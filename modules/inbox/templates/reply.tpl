<form action="{$CPURL}/{$cumodule.name}/reply/" class="form-horizontal form-ajax" role="form" method="post">
  <div class="bg-gray innerAll border-bottom">
      <div class="innerLR">
          <div class="form-group">
              <label for="to" class="col-sm-2 control-label">{"إلي"|gettext}</label>
              <div class="col-sm-10">
                  <input name="email" value="{$message.email}" type="text" class="form-control" readonly required />
              </div>
          </div>
          <div class="form-group">
              <label for="Cc" class="col-sm-2 control-label">{"العنوان"|gettext}</label>
              <div class="col-sm-10">
                  <input name="subject" type="text" class="form-control" required />
              </div>
          </div>
          <div class="clearfix"></div>
      </div>
  </div>
  <div class="innerAll inner-2x">
      <textarea name="message" class="notebook border-none form-control padding-none" rows="4" required placeholder="{"اكتب نص الرسالة المراد ارسالها"|gettext}"></textarea>
      <div class="clearfix"></div>
  </div>
  <div class="innerAll text-center border-top">
      <button type="submit" class="btn btn-primary"><i class="fa fa-fw icon-outbox-fill"></i> {"إرسال الآن"|gettext}</button>
      <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-fw icon-crossing"></i> {"إلغاء الإرسال"|gettext}</button>
  </div>
</form>