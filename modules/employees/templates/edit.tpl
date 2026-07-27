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
            <div class="col-md-4">
              <select name="nationality" class="ajaxSelect" required style="width:100%;height: 34px;">
                  <option value=""></option>
                {foreach $nationalities as $nationality}
                  <option value="{$nationality.id}" {if $data.nationality eq $nationality.id}selected="selected"{/if}>{$nationality.name}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"العنوان 1"|gettext}</label>
            <div class="col-md-8">
              <input class="form-control" name="address1" value="{$data.address1|clean}" type="text" required />
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
            <label class="col-md-3 control-label">{"رقم الهوية"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="idnum" type="text" value="{$data.idnum}" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"تاريخ البدء بالعمل"|gettext}</label>
            <div class="col-md-5 input-group">
              <input class="form-control datepicker" autocomplete="off" name="startdate" value="{$data.startdate|clean}" type="text" required />
              <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"المؤهل"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="education" value="{$data.education|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label shrink">{"نسبة العمولة للإيجار"|gettext}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="rentcomm" value="{$data.rentcomm|clean}" type="number" step="any" required />
              <span class="input-group-addon"><i class="fa fa-percent"></i></span>
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
            <label class="col-md-3 control-label">{"رقم هاتف المنزل"|gettext}</label>
            <div class="col-md-6 input-group">
              <input class="form-control" name="homephone" value="{$data.homephone|clean}" type="number" minlength="{$config.phone_length}" maxlength="13" placeholder="{$config.phone_code}xxxxxxxxx" />
              <span class="input-group-addon"><i class="fa fa-phone"></i></span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-3 control-label">{"اسم الأب"|gettext}</label>
            <div class="col-md-8">
              <input class="form-control" name="fathname" value="{$data.fathname|clean}" type="text" required tabindex="2" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"اسم العائلة"|gettext}</label>
            <div class="col-md-8">
              <input class="form-control" name="famname" value="{$data.famname|clean}" type="text" tabindex="4" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"بريد إلكتروني"|gettext}</label>
            <div class="col-md-6 input-group">
              <input class="form-control" name="email" value="{$data.email|clean}" type="email" />
              <span class="input-group-addon"><i class="fa fa-inbox"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"العنوان 2"|gettext}</label>
            <div class="col-md-9">
              <input class="form-control" name="address2" value="{$data.address2|clean}" type="text" maxlength="150" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"مصدرها"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="idsource" value="{$data.idsource|clean}" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"تاريخ إنتهاء الهوية"|gettext}</label>
            <div class="col-md-5 input-group">
              <input class="form-control datepicker" autocomplete="off" name="idexpire" value="{$data.idexpire|clean}" type="text" required />
              <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"التاريخ المعتمد"|gettext}</label>
            <div class="col-md-3">
              <select name="calendar" class="form-control calendarAutoChange" required>
                <option value="1">{"ميلادي"|gettext}</option>
                <option value="2" {if $data.calendar eq 2 OR (empty($data.calendar) AND $config.salary_calendar eq 2)}selected="selected"{/if}>{"هجري"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"الحالة الإجتماعية"|gettext}</label>
            <div class="col-md-4">
              <select name="maritalstatus" class="form-control" required>
                <option value="married" {if $data.maritalstatus eq "married"}selected="selected"{/if}>{"متزوج"|gettext}</option>
                <option value="single" {if $data.maritalstatus eq "single"}selected="selected"{/if}>{"أعزب"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"الراتب الشهري"|gettext}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="salary" value="{$data.salary|clean}" type="number" step="any" required />
              <span class="input-group-addon">{$config.currency}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"نسبة العمولة للبيع"|gettext}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="sellcomm" value="{$data.sellcomm|clean}" type="number" step="any" required />
              <span class="input-group-addon"><i class="fa fa-percent"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"جوال آخر"|gettext}</label>
            <div class="col-md-6 input-group">
              <input class="form-control" name="mobile2" value="{$data.mobile2|clean}" type="number" minlength="{$config.mobile_length}" maxlength="14" placeholder="{$config.phone_code}xxxxxxxxx" />
              <span class="input-group-addon"><i class="fa fa-mobile-phone"></i></span>
            </div>
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
