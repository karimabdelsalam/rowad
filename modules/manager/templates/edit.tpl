<form action="" class="form-horizontal margin-none form-ajax" method="post">
<input type="hidden" name="userid" value="{$smarty.get.id}" />
    <div class="widget">
        <div class="widget-head">
            <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
        </div>
        <div class="widget-body innerAll inner-2x">
            <div class="row innerLR">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"الاسم"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="name" value="{$manager.name|clean}" type="text" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"اسم المستخدم"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="username" value="{$manager.username|clean}" type="text" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"الصورة الشخصية"|gettext}</label>
                        <div class="col-md-8 input-group">
                            <input class="form-control" name="picture" type="file" />
                            {if !empty($manager.picture)}
                            <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$manager.picture|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
                            {/if}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"رقم الهوية"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="idnum" value="{$manager.idnum|clean}" type="number" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"لغة النظام"|gettext}</label>
                        <div class="col-md-5">
                            <select name="lang" class="form-control" required>
                                <option value="en">English</option>
                                <option value="ar" {if $manager.lang eq "ar"}selected="selected"{/if}>العربية</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"البريد الإلكتروني"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="email" value="{$manager.email|clean}" type="email" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"كلمة المرور"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="password" type="text" {if $cuaction.name eq "add"}required{/if} />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"رقم الجوال"|gettext}</label>
                        <div class="col-md-6">
                            <input class="form-control" name="mobile" value="{$manager.mobile|clean}" type="text" minlength="{$config.mobile_length}" maxlength="14" placeholder="{$config.phone_code}xxxxxxxxx" />
                        </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-3 control-label">{"الحالة"|gettext}</label>
                      <div class="col-md-6">
                        <input name="active" type="checkbox" class="ckeckonoff" value="{$config.active}" />
                      </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray innerAll inner-2x">
                <div class="row">
                {foreach from=$permissions item=permission name=modloop}
                    <div class="col-md-6">
                        <h4 class="perm-blocks"><span>{$permission.title|gettext|clean}</span></h4>
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-6">
                            {foreach from=$permission.actions item=action name=actloop}
                              <div class="checkbox">
                                  <label class="checkbox-custom">
                                      <i class="fa fa-fw fa-square-o"></i>
                                      <input type="checkbox" class="checkbox" name="{$permission.name|clean}_{$action.name|clean}" {if $action.permission eq 1 OR $cuaction.name eq "add"}checked="checked"{/if} />{$action.title|gettext|clean}
                                  </label>
                              </div>
                            {if $smarty.foreach.actloop.iteration%3 eq 0}</div><div class="col-md-6">{/if}
                            {/foreach}
                            </div>
                        </div>
                {if $smarty.foreach.modloop.iteration%2 eq 0}</div><div class="row">{/if}
                    </div>
                {/foreach}
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
