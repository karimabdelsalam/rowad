<div class="row row-app {if $smarty.get.quick_form eq 1}login-quick-form{/if}">
  <div class="col-separator col-unscrollable box">
    <div class="col-table">
      <h4 class="innerAll margin-none border-bottom text-center">
        <i class="fa fa-lock"></i> {"سجل دخولك بحسابك الآن"|gettext}
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
                  <form action="{$CPURL}/login/index" class="form-ajax login-form-toggle login-form-login" method="post" role="form">
                    <div class="form-group no-border-space">
                      <label>{"اسم المستخدم"|gettext}</label>
                      <input name="username" type="text" class="form-control" required />
                    </div>
                    <div class="form-group no-border-space">
                      <label>{"كلمة المرور"|gettext}</label>
                      <input name="password" type="password" class="form-control" required />
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">{"دخول"|gettext}</button>
                    <div class="clearfix"></div>
                    <div class="checkbox pull-left">
                      <label><input name="rememberme" type="checkbox" />{"تذكرني لاحقاً"|gettext}</label>
                    </div>
                    <div class="checkbox pull-right" style="margin-top:10px">
                      <label><a href="javascript:" class="show-toggle-form" data-form="login-form-recover">{"تذكر كلمة المرور !"|gettext}</a></label>
                    </div>
                  </form>
                  <form action="{$CPURL}/login/verifycode" class="form-ajax hide2" id="smsLogin" method="post" role="form">
                    <div class="alert alert-warning">{"ارسل النظام كود الدخول في رسالة قصيرة الى رقم جوالك"|gettext}</div>
                    <div class="form-group">
                      <input name="code" type="text" class="form-control" placeholder="{"ادخل كود الدخول"|gettext}" required />
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">{"دخول"|gettext}</button>
                  </form>
                  <form action="{$CPURL}/login/resetpwd" class="form-ajax login-form-toggle login-form-recover hide2" method="post" role="form">
                    <div class="form-group">
                      <input name="email" type="email" class="form-control" placeholder="{"ادخل بريدك الإلكتروني ليصلك عليه تعليمات استعادة حسابك"|gettext}" required />
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">{"استعادة كلمة المرور"|gettext}</button>
                    <div class="checkbox">
                      <p><a href="javascript:" class="show-toggle-form" data-form="login-form-login"><i class="fa fa-angle-right"></i> {"تسجيل الدخول"|gettext}</a></p>
                    </div>
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
