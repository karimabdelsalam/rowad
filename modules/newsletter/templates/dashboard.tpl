<form action="" class="form-horizontal margin-none" method="post">
    <div class="widget">
        <div class="widget-head">
            <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
        </div>
        <div class="widget-body innerAll">
            <div class="row innerLR">
                <div class="col-md-12">
                    <div class="form-group">
                        <label class="col-md-2 control-label">{"من"|gettext}</label>
                        <div class="col-md-4">
                            <input class="form-control" name="sender" value="{$config.email|clean}" type="email" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{"العنوان"|gettext}</label>
                        <div class="col-md-6">
                            <input class="form-control" name="subject" type="text" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-12">
                            <textarea class="form-control ajaxeditor" name="content" required></textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{"إرسال إلي"|gettext}</label>
                        <div class="col-md-3">
                            <h4>{"مجموعة المستخدمين"|gettext}</h4>
                            {foreach from=$usergroups item=usergroup name=op}
                            <div class="checkbox">
                                <label class="checkbox-custom">
                                    <i class="fa fa-fw fa-square-o"></i>
                                    <input type="checkbox" class="checkbox" name="usergroupid[]" value="{$usergroup.id}" /> {$usergroup.title|gettext|clean}
                                </label>
                            </div>
                            {/foreach}
                            <div class="checkbox">
                                <label class="checkbox-custom">
                                    <i class="fa fa-fw fa-square-o"></i>
                                    <input type="checkbox" class="checkbox" name="employees" />{"الموظفين"|gettext}
                                </label>
                            </div>
                            <div class="checkbox">
                                <label class="checkbox-custom">
                                    <i class="fa fa-fw fa-square-o"></i>
                                    <input type="checkbox" class="checkbox" name="buyer" />{"العملاء"|gettext}
                                </label>
                            </div>
                            <div class="checkbox">
                                <label class="checkbox-custom">
                                    <i class="fa fa-fw fa-square-o"></i>
                                    <input type="checkbox" class="checkbox" name="owner" />{"ملاك العقارات"|gettext}
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h4>{"القائمة البريدية"|gettext}</h4>
                            <div class="checkbox">
                                <label class="checkbox-custom">
                                    <i class="fa fa-fw fa-square-o"></i>
                                    <input type="checkbox" class="checkbox" name="email_list" /> {"تفعيل الارسال الى جميع مشتركي القائمة البريدية"|gettext}
                                </label>
                            </div>
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
