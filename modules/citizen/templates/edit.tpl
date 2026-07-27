<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <input type="hidden" name="tempid" value="{$tempid}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-3 control-label">{"أسم المواطن"|gettext}</label>
            <div class="col-md-8">
              <input class="form-control" name="title" value="{$data.title|clean}" type="text" required />
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
              <input class="form-control" name="idcopy" type="file" />
              {if !empty($data.idcopy)}
                <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$data.idcopy|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
              {/if}
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
        </div>
        <div class="col-md-6">
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
            <label class="col-md-3 control-label">{"رقم الهوية"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="idnumber" value="{$data.idnumber|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">{"مصدرها"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="idsource" value="{$data.idsource|clean}" type="text" />
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
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
