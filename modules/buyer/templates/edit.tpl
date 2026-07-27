<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="tempid" value="{$tempid}" />
  <input type="hidden" name="id" value="{$data.id}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-3 control-label">{"صفة العميل"|gettext}</label>
            <div class="col-md-5">
              <select name="type" class="form-control" required>
                <option value="person" {if $data.type eq "person"}selected="selected"{/if}>{"شخص"|gettext}</option>
                <option value="company" {if $data.type eq "company"}selected="selected"{/if}>{"شركة"|gettext}</option>
                <option value="organization" {if $data.type eq "organization"}selected="selected"{/if}>{"مؤسسة"|gettext}</option>
                <option value="government" {if $data.type eq "government"}selected="selected"{/if}>{"دائرة حكومية"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group hide2 companyFields">
            <label class="col-md-3 control-label">{"اسم الجهة"|gettext}</label>
            <div class="col-md-8">
              <input class="form-control" name="company" value="{$data.company|clean}" type="text" required />
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group hide2 companyFields">
            <label class="col-md-3 control-label">{"رقم السجل"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="com_idnumber" value="{$data.com_idnumber|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group hide2 companyFields">
            <label class="col-md-3 control-label">{"رقم الجوال"|gettext}</label>
            <div class="col-md-6 input-group">
              <input class="form-control" name="com_mobile" value="{$data.com_mobile|clean}" type="number" minlength="{$config.mobile_length}" maxlength="14" placeholder="{$config.phone_code}xxxxxxxxx" required />
              <span class="input-group-addon"><i class="fa fa-mobile"></i></span>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <h4 class="innerAll bg-container margin-none hide2 companyFields"><i class="fa fa-user"></i> {"يمثلها"|gettext}</h4>
        <div class="separator hide2 companyFields"></div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-3 control-label">{"الاسم الأول"|gettext}</label>
            <div class="col-md-8">
              <input class="form-control" name="fname" value="{$data.fname|clean}" type="text" required tabindex="1" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"اسم الجد"|gettext}</label>
            <div class="col-md-8">
              <input class="form-control" name="lname" value="{$data.lname|clean}" type="text" tabindex="3" />
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
            <label class="col-md-3 control-label">{"مصدرها"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="idsource" value="{$data.idsource|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"صورة من الهوية"|gettext}</label>
            <div class="col-md-8 input-group">
              <input class="form-control" name="idcopy" type="file" />
              {if !empty($data.idcopy)}
                <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$data.idcopy|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
              {/if}
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"جهة العمل"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="work" value="{$data.work|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"رقم هاتف المنزل"|gettext}</label>
            <div class="col-md-6 input-group">
              <input class="form-control" name="homephone" value="{$data.homephone|clean}" type="number" minlength="9" maxlength="9" placeholder="01xxxxxxx" />
              <span class="input-group-addon"><i class="fa fa-phone"></i></span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-3 control-label">{"أسم الأب"|gettext}</label>
            <div class="col-md-8">
              <input class="form-control" name="fathname" value="{$data.fathname|clean}" type="text" required tabindex="2" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"أسم العائلة"|gettext}</label>
            <div class="col-md-8">
              <input class="form-control" name="famname" value="{$data.famname|clean}" type="text" tabindex="4" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"عنوان السكن"|gettext}</label>
            <div class="col-md-9">
              <input class="form-control" name="address" value="{$data.address|clean}" type="text" maxlength="150" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"رقم الهوية"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="idnumber" value="{$data.idnumber|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"تاريخ إصدارها"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control datepicker" autocomplete="off" name="iddate" value="{$data.iddate|clean}" type="text" required />
            </div>
            <div class="col-md-3">
              <select name="calendar" class="form-control calendarAutoChange" required>
                <option value="1">{"ميلادي"|gettext}</option>
                <option value="2" {if $data.calendar eq 2 OR (empty($data.calendar) AND $config.system_calendar eq 2)}selected="selected"{/if}>{"هجري"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"رقم الجوال"|gettext}</label>
            <div class="col-md-6 input-group">
              <input class="form-control" name="mobile" value="{$data.mobile|clean}" type="number" minlength="{$config.mobile_length}" maxlength="14" placeholder="{$config.phone_code}xxxxxxxxx" required />
              <span class="input-group-addon"><i class="fa fa-mobile"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"رقم هاتف العمل"|gettext}</label>
            <div class="col-md-6 input-group">
              <input class="form-control" name="workphone" value="{$data.workphone|clean}" type="number" minlength="{$config.phone_length}" maxlength="13" placeholder="{$config.phone_code}xxxxxxxxx" />
              <span class="input-group-addon"><i class="fa fa-phone"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"بريد إلكتروني"|gettext}</label>
            <div class="col-md-6 input-group">
              <input class="form-control" name="email" value="{$data.email|clean}" type="email" />
              <span class="input-group-addon"><i class="fa fa-inbox"></i></span>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <h4 class="innerAll bg-container margin-none"><i class="fa fa-file-text-o"></i> {"معلومات الفوترة"|gettext}</h4>
        <div class="separator"></div>
        <div class="col-md-4">
          <div class="form-group">
            <label class="col-md-3 control-label">{"رقم المبني"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="billing_info[buildno]" value="{$data.billing_info.buildno|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"اسم الشارع"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="billing_info[street]" value="{$data.billing_info.street|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"الحي"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="billing_info[district]" value="{$data.billing_info.district|clean}" type="text" />
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label class="col-md-3 control-label">{"المدينة"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="billing_info[city]" value="{$data.billing_info.city|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"الرمز البريدي"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="billing_info[postalcode]" value="{$data.billing_info.postalcode|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"الرقم الإضافي"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="billing_info[addno]" value="{$data.billing_info.addno|clean}" type="text" placeholder="{"الرقم الإضافي للعنوان"|gettext}" />
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label class="col-md-3 control-label">{"الرقم الضريبي"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="billing_info[taxno]" value="{$data.billing_info.taxno|clean}" type="text" placeholder="{"رقم تسجيل ضريبة القيمة المضافة"|gettext}" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"معرف آخر"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="billing_info[otherid]" value="{$data.billing_info.otherid|clean}" type="text" />
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
                <button href="{$CPURL}/buyer/reset/?id={$data.userid}" data-load="{"جارى الإرسال"|gettext}" data-success="{"تم بنجاح"|gettext}" class="btn btn-success instantReq">
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
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-upload"></i> {"إرفاق مستندات أخرى"|gettext}</h4>
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
