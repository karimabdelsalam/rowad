<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"الاسم بالكامل"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="name" value="{$manager.name|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"البريد الإلكتروني"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" value="{$manager.email|clean}" type="email" readonly />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم المستخدم"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control" value="{$manager.username|clean}" type="text" readonly />
            </div>
          </div>
          {if $smarty.session.userinfo.groupid eq 2}
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم الجهة"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" value="{$manager.orgname|clean}" type="text" readonly />
            </div>
          </div>
          {/if}
          <div class="form-group">
            <label class="col-md-2 control-label">{"كلمة المرور"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control" name="password" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"لغة النظام"|gettext}</label>
            <div class="col-md-2">
              <select name="lang" class="form-control" required>
                <option value="en">English</option>
                <option value="ar" {if $manager.lang eq "ar"}selected="selected"{/if}>العربية</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"رقم الهاتف"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control" name="mobile" value="{$manager.mobile|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"الصورة الشخصية"|gettext}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="picture" type="file" />
              {if !empty($manager.picture)}
                <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$manager.picture|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
                  {/if}
            </div>
          </div>
        </div>
      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ التغييرات"|gettext}</button>
        <button type="reset" class="btn btn-default"><i class="fa fa-times"></i> {"استرجاع"|gettext}</button>
      </div>
    </div>
  </div>
</form>
