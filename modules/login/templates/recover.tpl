<div class="row row-app">
  <div class="col-separator col-unscrollable box">
    <div class="col-table">
      <h4 class="innerAll margin-none border-bottom text-center">
        <i class="fa fa-lock"></i> {"نموذج استرجاع كلمة المرور"|gettext}
      </h4>
      <div class="col-table-row">
        <div class="col-app col-unscrollable">
          <div class="col-app">
            <div class="login">
              <div class="placeholder text-center"><i class="fa fa-lock"></i></div>
              <div class="panel panel-default col-md-4 col-sm-6 col-sm-offset-3 col-md-offset-4">
                <div class="smartiocp-loading" style="margin-top:5px;"><img src="{$image_path}/loading.gif" style="border:0;" alt="" /></div>
                <!--
                <div class="" style="margin-top:5px;text-align: center"><images src="{$MEDIAURL}/{$config.logo|clean}" style="width: auto;border: 0;max-width: 20%;max-height: 200px;" alt="" /></div>
                -->
                <div class="panel-body">
                  <form action="{$CPURL}/login/recover/{$recoverHash}" class="form-ajax" method="post" role="form">
                    <div class="form-group no-border-space">
                      <label>{"كلمة المرور الجديدة"|gettext}</label>
                      <input name="newpassword" type="password" class="form-control" required />
                    </div>
                    <div class="form-group no-border-space">
                      <label>{"تأكيد كلمة المرور"|gettext}</label>
                      <input name="newpassword2" type="password" class="form-control" required />
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">{"تحديث كلمة المرور"|gettext}</button>
                    <div class="clearfix"></div>
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
