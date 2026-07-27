<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseContainer0"><i class="fa fa-minus-square-o"></i> {"إعدادات عامة"|gettext}</h4>
        <div class="separator"></div>
        <div class="innerAll collapseContainer0">
          <div class="form-group">
            <label class="col-md-3 control-label">{"اسم المكتب بالعربية"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="sitename" value="{$config.sitename|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"اسم المكتب بالأنجليزية"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="en_sitename" value="{$config.en_sitename|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"البريد الإلكتروني"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control ltr" name="email" value="{$config.email|clean}" type="email" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"ايقونة الشعار"|gettext} {"الأيقونة التي تظهر بجوار مسمى الموقع في لسان المتصفح. المقاس القياسي 48*48 بكسل"|gettext|help}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="favicon" type="file" />
              <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$config.favicon|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"شعار المكتب"|gettext} {"يوضع في العقود والخطابات"|gettext|help}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="logo" type="file" />
              <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$config.logo|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"ملف اللغة العربية"|gettext} {"يجب ان يكون امتداد الملف من نوع MO"|gettext|help}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="lang_file_ar" type="file" />
              {if !empty($config.lang_file_ar)}
              <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$config.lang_file_ar|clean}" target="_blank"><i class="fa fa-download"></i></a></span>
              {/if}
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"ملف اللغة الإنجليزية"|gettext} {"يجب ان يكون امتداد الملف من نوع MO"|gettext|help}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="lang_file_en" type="file" />
              {if !empty($config.lang_file_en)}
              <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$config.lang_file_en|clean}" target="_blank"><i class="fa fa-download"></i></a></span>
              {/if}
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"حالة الخدمة"|gettext}</label>
            <div class="col-md-6">
              <input name="active" type="checkbox" class="ckeckonoff" value="{$config.active|clean}" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"رسالة الإغلاق"|gettext}</label>
            <div class="col-md-6">
              <textarea class="form-control" name="closemsg" rows="6">{$config.closemsg|clean}</textarea>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"الدولة"|gettext}</label>
            <div class="col-md-5">
              <select class="form-control ajaxSelect" name="country" required>
                {foreach $nationalities as $nationality}
                  <option value="{$nationality.id}" {if $config.country eq $nationality.id}selected="selected"{/if}>{$nationality.name}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"المنطقة الزمنية"|gettext}</label>
            <div class="col-md-2">
              <select class="form-control ajaxSelect" name="timezone" required>
                {include file="core/templates/timezone.tpl"}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"شكل لوحة التحكم"|gettext}</label>
            <div class="col-md-3">
              <select name="style" class="form-control">
                <option value="modern">{"مودرن"|gettext}</option>
                <option value="classic" {if $config.style eq "classic"}selected="selected"{/if}>{"كلاسيكي"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"العملة المستخدمة"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control" name="currency" value="{$config.currency|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"سابقة رقم تذكرة الدعم الفني"|gettext} {"المقطع النصي الذي يسبق رقم التذكرة و يمكن تركه فارغاً ليتم توليده بشكل عشوائي من قبل النظام"|gettext|help}</label>
            <div class="col-md-2">
              <input class="form-control" name="support_ticket_prefix" value="{$config.support_ticket_prefix|clean}" placeholder="PHA-XXXXX" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"ضريبة الدخل لأرباح المؤسسة"|gettext}</label>
            <div class="col-md-1">
              <input name="income_tax_status" type="checkbox" class="ckeckonoff" value="{$config.income_tax_status|clean}" />
            </div>
            <div class="col-md-2 input-group incomeTaxField">
              <input class="form-control" name="income_tax_value" value="{$config.income_tax_value|clean}" type="number" step="any" required />
              <span class="input-group-addon"><i class="fa fa-percent"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"ضريبة الدخل لمالك العقار"|gettext}</label>
            <div class="col-md-1">
              <input name="owner_tax_status" type="checkbox" class="ckeckonoff" value="{$config.owner_tax_status|clean}" />
            </div>
            <div class="col-md-2 input-group incomeOwnerTaxField">
              <input class="form-control" name="owner_tax_value" value="{$config.owner_tax_value|clean}" type="number" step="any" required />
              <span class="input-group-addon"><i class="fa fa-percent"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"ضريبة القيمة المضافة للسكن"|gettext}</label>
            <div class="col-md-1">
              <input name="habit_tax_status" type="checkbox" class="ckeckonoff" value="{$config.habit_tax_status|clean}" />
            </div>
            <div class="col-md-2 input-group habitTaxField">
              <input class="form-control" name="habit_tax_value" value="{$config.habit_tax_value|clean}" type="number" step="any" required />
              <span class="input-group-addon"><i class="fa fa-percent"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"ضريبة القيمة المضافة للتجاري"|gettext}</label>
            <div class="col-md-1">
              <input name="comm_tax_status" type="checkbox" class="ckeckonoff" value="{$config.comm_tax_status|clean}" />
            </div>
            <div class="col-md-2 input-group commTaxField">
              <input class="form-control" name="comm_tax_value" value="{$config.comm_tax_value|clean}" type="number" step="any" required />
              <span class="input-group-addon"><i class="fa fa-percent"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"ضريبة القيمة المضافة للخدمات"|gettext}</label>
            <div class="col-md-2 input-group">
              <input class="form-control" name="vat" value="{$config.vat|clean}" type="number" step="any" />
              <span class="input-group-addon"><i class="fa fa-percent"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"دخول آمن OTP للمدراء"|gettext}</label>
            <div class="auto-width inline-checkbox">
              <input name="smslogin" type="checkbox" class="ckeckonoff" value="{$config.smslogin|clean}" />
            </div>
            <div class="col-md-8 marginTB">
              <p class="margin-none">{"الدخول عن طريق كود أمان عند محاولة الدخول من جهاز مختلف غير موثوق"|gettext}</p>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"دخول آمن OTP للعملاء"|gettext}</label>
            <div class="auto-width inline-checkbox">
              <input name="smslogin_clients" type="checkbox" class="ckeckonoff" value="{$config.smslogin_clients|clean}" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"تفعيل الرسائل النصية"|gettext}</label>
            <div class="col-md-4">
              <input name="sms_status" type="checkbox" class="ckeckonoff" value="{$config.sms_status|clean}" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"مفتاح الدولة"|gettext} {"رقم المفتاح الهاتفي الخاص بالدولة والذي يسبق رقم الهاتف الأرضي او الجوال"|gettext|help}</label>
            <div class="col-md-2 input-group">
              <input class="form-control" name="phone_code" value="{$config.phone_code|clean}" type="number" required />
              <span class="input-group-addon"><i class="fa fa-key"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"طول رقم الهاتف"|gettext} {"عدد الأرقام التي يتكون منها رقم الهاتف الأرضي مضاف له مفتاح الدولة"|gettext|help}</label>
            <div class="col-md-2 input-group">
              <input class="form-control" name="phone_length" value="{$config.phone_length|clean}" type="number" min="5" required />
              <span class="input-group-addon"><i class="fa fa-phone"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"طول رقم الجوال"|gettext} {"عدد الأرقام التي يتكون منها رقم الهاتف الجوال مضاف له مفتاح الدولة"|gettext|help}</label>
            <div class="col-md-2 input-group">
              <input class="form-control" name="mobile_length" value="{$config.mobile_length|clean}" type="number" min="5" required />
              <span class="input-group-addon"><i class="fa fa-mobile-phone"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"التاريخ المعتمد في البرنامج"|gettext}</label>
            <div class="col-md-3">
              <select name="system_calendar" class="form-control">
                <option value="1">{"التاريخ الميلادي"|gettext}</option>
                <option value="2" {if $config.system_calendar eq 2}selected="selected"{/if}>{"التاريخ الهجري (أم القرى)"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"التاريخ المعتمد في كتابة العقود"|gettext}</label>
            <div class="col-md-3">
              <select name="contract_calendar" class="form-control">
                <option value="1">{"التاريخ الميلادي"|gettext}</option>
                <option value="2" {if $config.contract_calendar eq 2}selected="selected"{/if}>{"التاريخ الهجري (أم القرى)"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"التاريخ المعتمد لسندات القبض"|gettext}</label>
            <div class="col-md-3">
              <select name="credit_calendar" class="form-control">
                <option value="1">{"التاريخ الميلادي"|gettext}</option>
                <option value="2" {if $config.credit_calendar eq 2}selected="selected"{/if}>{"التاريخ الهجري (أم القرى)"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"التاريخ المعتمد لسندات الصرف"|gettext}</label>
            <div class="col-md-3">
              <select name="debit_calendar" class="form-control">
                <option value="1">{"التاريخ الميلادي"|gettext}</option>
                <option value="2" {if $config.debit_calendar eq 2}selected="selected"{/if}>{"التاريخ الهجري (أم القرى)"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"التاريخ المعتمد للرواتب"|gettext}</label>
            <div class="col-md-3">
              <select name="salary_calendar" class="form-control">
                <option value="1">{"التاريخ الميلادي"|gettext}</option>
                <option value="2" {if $config.salary_calendar eq 2}selected="selected"{/if}>{"التاريخ الهجري (أم القرى)"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"التاريخ المعتمد للفواتير"|gettext}</label>
            <div class="col-md-3">
              <select name="invoices_calendar" class="form-control">
                <option value="1">{"التاريخ الميلادي"|gettext}</option>
                <option value="2" {if $config.invoices_calendar eq 2}selected="selected"{/if}>{"التاريخ الهجري (أم القرى)"|gettext}</option>
              </select>
            </div>
          </div>

        </div>

        <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseContainerQRCode"><i class="fa fa-plus-square-o"></i> {"بيانات العقد الإلكترونية"|gettext}</h4>
        <div class="separator"></div>
        <div class="innerAll collapseContainerQRCode" style="display:none">
          <div class="form-group">
            <label class="col-md-3 control-label">{"تفعيل كود الأمان عبر الرسائل النصية"|gettext}</label>
            <div class="col-md-4">
              <input name="ec_sms_passcode" type="checkbox" class="ckeckonoff" value="{$config.ec_sms_passcode|clean}" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"عدد المرات المتاحة في اليوم"|gettext} {"عدد المرات المتاحة لمالك العقار والعميل لطلب كود الأمان عبر رسائل SMS في اليوم"|gettext|help}</label>
            <div class="col-md-2 input-group">
              <input class="form-control" name="ec_sms_tries" value="{$config.ec_sms_tries}" type="number" min="1" required />
              <span class="input-group-addon"><i class="fa fa-refresh"></i></span>
            </div>
          </div>
        </div>

        <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseContainer5"><i class="fa fa-plus-square-o"></i> {"إعدادات مزود SMS"|gettext}</h4>
        <div class="separator"></div>
        <div class="innerAll collapseContainer5" style="display:none">
          <div class="form-group">
            <label class="col-md-3 control-label">{"مزود الخدمة"|gettext}</label>
            <div class="col-md-3">
              <select class="form-control" name="sms_provider">
                <option value="">{"اختر مزود الخدمة"|gettext}</option>
                <option value="mobilysms" {if $config.sms_provider eq "mobilysms"}selected="selected"{/if}>{"رسائل موبايلي"|gettext}</option>
                <option value="unifonic" {if $config.sms_provider eq "unifonic"}selected="selected"{/if}>{"يونيفونيك"|gettext}</option>
                <option value="clickatell" {if $config.sms_provider eq "clickatell"}selected="selected"{/if}>Clickatell</option>
                <option value="smsglobal" {if $config.sms_provider eq "smsglobal"}selected="selected"{/if}>smsGlobal</option>
                <option value="twilio" {if $config.sms_provider eq "twilio"}selected="selected"{/if}>Twilio</option>
                <option value="bulksms" {if $config.sms_provider eq "bulksms"}selected="selected"{/if}>Bulksms</option>
                <option value="smsapi" {if $config.sms_provider eq "smsapi"}selected="selected"{/if}>SMSAPI</option>
                <option value="msg91" {if $config.sms_provider eq "msg91"}selected="selected"{/if}>MSG91</option>
                <option value="nexmo" {if $config.sms_provider eq "nexmo"}selected="selected"{/if}>Nexmo</option>
                <option value="clicksend" {if $config.sms_provider eq "clicksend"}selected="selected"{/if}>Clicksend</option>
              </select>
            </div>
            <div class="col-md-6 smsProvider_settings">
              <a href="javascript:" target="_blank" class="smsProvider_link btn btn-default inline-block"><i class="fa fa-briefcase"></i> {"موقع مزود الخدمة"|gettext}</a>
              <button type="button" class="btn btn-success getSMSBalance inline-block"><i class="fa fa-balance-scale"></i>{"عرض الرصيد"|gettext}</button>
            </div>
          </div>
          <div class="form-group smsProvider_sender smsProvider_settings">
            <label class="col-md-3 control-label">{"اسم المرسل"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="sms_sender" type="text" value="{$config.sms_sender}" />
            </div>
          </div>
          <div class="form-group smsProvider_key smsProvider_settings">
            <label class="col-md-3 control-label">{"اسم المستخدم"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="sms_user" type="text" value="{$config.sms_user}" />
            </div>
          </div>
          <div class="form-group smsProvider_secret smsProvider_settings">
            <label class="col-md-3 control-label">{"كلمة المرور"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="sms_pass" type="password" value="{$config.sms_pass}" />
            </div>
          </div>
        </div>

        <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseContainer6"><i class="fa fa-plus-square-o"></i> {"بيانات مراسلات المكتب"|gettext}</h4>
        <div class="separator"></div>
        <div class="innerAll collapseContainer6" style="display:none">
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم المدينة"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="address1" type="text" value="{$config.address1}" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"الشارع"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="address2" type="text" value="{$config.address2}" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"رقم الهاتف"|gettext}</label>
            <div class="col-md-3 input-group">
              <input class="form-control" name="phone" value="{$config.phone|clean}" type="number" minlength="{$config.phone_length}" maxlength="13" placeholder="{$config.phone_code}xxxxxxxxx" />
              <span class="input-group-addon"><i class="fa fa-phone"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"رقم الفاكس"|gettext}</label>
            <div class="col-md-3 input-group">
              <input class="form-control" name="fax" value="{$config.fax|clean}" type="number" minlength="{$config.phone_length}" maxlength="13" placeholder="{$config.phone_code}xxxxxxxxx" />
              <span class="input-group-addon"><i class="fa fa-fax"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"رقم الجوال"|gettext}</label>
            <div class="col-md-3 input-group">
              <input class="form-control" name="mobile" value="{$config.mobile|clean}" type="number" minlength="{$config.mobile_length}" maxlength="14" placeholder="{$config.phone_code}xxxxxxxxx" />
              <span class="input-group-addon"><i class="fa fa-mobile"></i></span>
            </div>
          </div>
        </div>

        <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseContainerRestAPI"><i class="fa fa-plus-square-o"></i> {"بوابة الإتصال"|gettext}</h4>
        <div class="separator"></div>
        <div class="innerAll collapseContainerRestAPI" style="display:none">
          <div class="form-group">
            <label class="col-md-3 control-label">{"مفتاح الإتصال"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="rest_api_key" value="{$config.rest_api_key}" type="text" readonly onclick="$(this).select()" />
            </div>
          </div>
        </div>

        <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseContainer7"><i class="fa fa-plus-square-o"></i> {"معلومات الفوترة"|gettext}</h4>
        <div class="separator"></div>
        <div class="innerAll collapseContainer7" style="display:none">
          <div class="col-md-4">
            <div class="form-group">
              <label class="col-md-3 control-label">{"رقم المبني"|gettext}</label>
              <div class="col-md-7">
                <input class="form-control" name="billing_info[buildno]" value="{$config.billing_info.buildno|clean}" type="text" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"اسم الشارع"|gettext}</label>
              <div class="col-md-7">
                <input class="form-control" name="billing_info[street]" value="{$config.billing_info.street|clean}" type="text" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"الحي"|gettext}</label>
              <div class="col-md-7">
                <input class="form-control" name="billing_info[district]" value="{$config.billing_info.district|clean}" type="text" />
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="col-md-3 control-label">{"المدينة"|gettext}</label>
              <div class="col-md-7">
                <input class="form-control" name="billing_info[city]" value="{$config.billing_info.city|clean}" type="text" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"الرمز البريدي"|gettext}</label>
              <div class="col-md-7">
                <input class="form-control" name="billing_info[postalcode]" value="{$config.billing_info.postalcode|clean}" type="text" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"الرقم الإضافي"|gettext}</label>
              <div class="col-md-7">
                <input class="form-control" name="billing_info[addno]" value="{$config.billing_info.addno|clean}" type="text" placeholder="{"الرقم الإضافي للعنوان"|gettext}" />
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="col-md-3 control-label">{"الرقم الضريبي"|gettext}</label>
              <div class="col-md-7">
                <input class="form-control" name="billing_info[taxno]" value="{$config.billing_info.taxno|clean}" type="text" placeholder="{"رقم تسجيل ضريبة القيمة المضافة"|gettext}" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"معرف آخر"|gettext}</label>
              <div class="col-md-7">
                <input class="form-control" name="billing_info[otherid]" value="{$config.billing_info.otherid|clean}" type="text" />
              </div>
            </div>
          </div>
        </div>

      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ التغييرات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
