<form action="" class="form-horizontal margin-none form-ajax" method="post">
    <input type="hidden" name="tempid" value="{$tempid}"/>
    <input type="hidden" name="id" value="{$data.id}"/>
    <div class="widget">
        <div class="widget-head">
            <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
        </div>
        <div class="widget-body innerAll">
            <div class="row innerLR">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"الاسم الاول"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="fname" value="{$data.fname|clean}" type="text" required tabindex="1"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"اسم الجد"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="lname" value="{$data.lname|clean}" type="text" tabindex="3"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"الجنسية"|gettext}</label>
                        <div class="col-md-6">
                            <select name="nationality" class="ajaxSelect" required style="height: 34px;width:100%">
                                <option value=""></option>
                                {foreach $nationalities as $nationality}
                                    <option value="{$nationality.id}" {if $data.nationality eq $nationality.id}selected="selected"{/if}>{$nationality.name}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"نوع الهوية"|gettext}</label>
                        <div class="col-md-5">
                            <select name="idtype" class="form-control" required>
                                <option value="idcard" {if $data.idtype eq "idcard"}selected="selected"{/if}>{"هوية وطنية"|gettext}</option>
                                <option value="passport" {if $data.idtype eq "passport"}selected="selected"{/if}>{"جواز سفر"|gettext}</option>
                                <option value="visa" {if $data.idtype eq "visa"}selected="selected"{/if}>{"إقامة"|gettext}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"صورة من الهوية"|gettext}</label>
                        <div class="col-md-8 input-group">
                            <input class="form-control" name="idcopy" type="file"/>
                            {if !empty($data.idcopy)}
                                <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$data.idcopy|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
                            {/if}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"جهة العمل"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="work" value="{$data.work|clean}" type="text"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"رقم هاتف المنزل"|gettext}</label>
                        <div class="col-md-6 input-group">
                            <input class="form-control" name="homephone" value="{$data.homephone|clean}" type="number" minlength="9" maxlength="9" placeholder="01xxxxxxx"/>
                            <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"اسم الأب"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="fathname" value="{$data.fathname|clean}" type="text" required tabindex="2"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"اسم العائلة"|gettext}</label>
                        <div class="col-md-8">
                            <input class="form-control" name="famname" value="{$data.famname|clean}" type="text" tabindex="4"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"عنوان السكن"|gettext}</label>
                        <div class="col-md-9">
                            <input class="form-control" name="address" value="{$data.address|clean}" type="text" maxlength="150"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"رقمها"|gettext}</label>
                        <div class="col-md-6">
                            <input class="form-control" name="idnumber" value="{$data.idnumber|clean}" type="text" required/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"رقم الجوال"|gettext}</label>
                        <div class="col-md-6 input-group">
                            <input class="form-control" name="mobile" value="{$data.mobile|clean}" type="number" minlength="{$config.mobile_length}" maxlength="14" placeholder="{$config.phone_code}xxxxxxxxx" required/>
                            <span class="input-group-addon"><i class="fa fa-mobile"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"رقم هاتف العمل"|gettext}</label>
                        <div class="col-md-6 input-group">
                            <input class="form-control" name="workphone" value="{$data.workphone|clean}" type="number" minlength="{$config.phone_length}" maxlength="13" placeholder="{$config.phone_code}xxxxxxxxx"/>
                            <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"بريد إلكتروني"|gettext}</label>
                        <div class="col-md-6 input-group">
                            <input class="form-control" name="email" value="{$data.email|clean}" type="email"/>
                            <span class="input-group-addon"><i class="fa fa-inbox"></i></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row innerLR">
                <h4 class="innerAll bg-container margin-none"><i class="fa fa-key"></i> {"بيانات الدخول"|gettext}</h4>
                <div class="separator"></div>
                <div class="form-group inline-flex flex">
                    <label class="col-md-3 control-label">{"اسم المستخدم"|gettext}</label>
                    <div class="col-md-4 input-group">
                        <input class="form-control" name="username" value="{$account.username|clean}" type="text" minlength="5" maxlength="50" autocomplete="new-password" placeholder="{$config.phone_code}xxxxxxxxx" />
                        <span class="input-group-addon"><i class="fa fa-user"></i></span>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-default" id="copyMobileNumber"><i class="fa fa-copy"></i> {"نسخ رقم الجوال"|gettext}</button>
                    </div>
                </div>
                <div class="form-group flex">
                    <label class="col-md-3 control-label">{"كلمة المرور"|gettext}</label>
                    <div class="col-md-4 input-group">
                        <input class="form-control" name="password" type="password" minlength="6" autocomplete="new-password" />
                        <span class="input-group-addon"><i class="fa fa-lock"></i></span>
                    </div>
                    {if !empty($data.userid)}
                    <div class="col-md-3">
                        <button href="{$CPURL}/owner/reset/?id={$data.userid}" data-load="{"جارى الإرسال"|gettext}" data-success="{"تم بنجاح"|gettext}" class="btn btn-success instantReq">
                            <i class="fa fa-send"></i> {"استعادة بيانات الدخول"|gettext} {"اعادة تعيين كلمة المرور و ارسال بيانات الدخول الى جوال المستخدم و البريد الإلكتروني المسجل"|gettext|help}
                        </button>
                    </div>
                    {/if}
                </div>
                <div class="form-group">
                    <label class="col-md-3 control-label">{"حالة الحساب"|gettext}</label>
                    <div class="col-md-3">
                        <input name="active" type="checkbox" class="ckeckonoff" value="{if !empty($data.userid)}{$account.active|clean}{else}1{/if}" />
                    </div>
                </div>
            </div>
            <div class="row innerLR">
                <h4 class="innerAll bg-container margin-none"><i class="fa fa-upload"></i> {"إرفاق مستندات أخري"|gettext}</h4>
                <div class="separator"></div>
                <ul class="file-uploaded-zone list-unstyled resume-documents">
                    {foreach $data.files as $file}
                        <li><i class="trashbtn fa fa-trash-o pull-right delete-ajax" href="{$CPURL}/{$smarty.const.Module}/delattach?id={$file.id}" data-parent="li"></i><a href="{$smarty.const.MEDIAURL}/{$file.path}" target="_blank"><i class="fa fa-file-o"></i> {$file.name} <span>{$file.timepost|ardate:false:false}</span></a></li>
                    {/foreach}
                </ul>
                <button type="button" href="{$CPURL}/home/attach?tempid={$tempid}" class="btn btn-default pull-right open-ajax" title="{"إرفاق ملف جديد"|gettext}"><i class="fa fa-cloud-upload"></i> {"إرفاق ملف جديد"|gettext}</button>
            </div>
            <div class="separator"></div>
            <div class="innerAll border-top">
                <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
            </div>
        </div>
    </div>
</form>
