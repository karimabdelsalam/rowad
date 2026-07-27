<div class="row row-app">
  <div class="col-separator col-unscrollable box">
    <div class="col-table">
      <h4 class="innerAll margin-none border-bottom text-center">
        <i class="fa fa-lock"></i> {"الدخول المشفر والآمن الى بيانات العقد"|gettext}
      </h4>
      <div class="col-table-row">
        <div class="col-app col-unscrollable">
          <div class="col-app">
            <div class="login">
              <div class="placeholder text-center"><i class="fa fa-lock"></i></div>
              <div class="panel panel-default col-md-4 col-sm-6 col-sm-offset-3 col-md-offset-4">
                <div class="smartiocp-loading" style="margin-top:5px;"><img src="{$image_path}/loading.gif" style="border:0;" alt="" /></div>
                <div class="panel-body">
                  <form action="{$CPURL}/client/" class="" id="smsLogin" method="post" role="form">
                    <input type="hidden" name="secret" value="{$secret}">
                    <div class="alert alert-warning">{"ارسل النظام كود التأمين في رسالة قصيرة الى رقم جوالك"|gettext}</div>
                    <div class="form-group">
                      <input name="passcode" type="text" value="{$testpasscode}" class="form-control" placeholder="{"ادخل كود التأمين"|gettext}" required />
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">{"تأكيد الدخول"|gettext}</button>
                  </form>
                </div>
              </div>
              <div class="clearfix"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
