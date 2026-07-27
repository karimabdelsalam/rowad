<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم المستند"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="title" value="{$data.title|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"رقم المستند"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control" name="number" value="{$data.number|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"تاريخ الإنتهاء"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control datepicker" autocomplete="off" name="expiredate" value="{$data.expiredate|clean}" type="text" required />
            </div>
            <div class="col-md-2">
              <select name="calendar" class="form-control calendarAutoChange" required>
                <option value="1">{"ميلادي"|gettext}</option>
                <option value="2" {if $data.calendar eq 2 OR (empty($data.calendar) AND $config.system_calendar eq 2)}selected="selected"{/if}>{"هجري"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"إرفاق ملف"|gettext}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="copy" type="file" {if empty($data.copy)}required{/if} />
              {if !empty($data.copy)}
                <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$data.copy|clean}" target="_blank"><i class="fa fa-search-plus"></i></a></span>
              {/if}
            </div>
          </div>
           <div class="form-group">
            <label class="col-md-2 control-label">{"تفعيل التنبيه"|gettext}</label>
            <div class="auto-width inline-checkbox">
              <input name="notify" type="checkbox" class="ckeckonoff" value="{$data.notify|clean}" />
            </div>
            <div class="col-md-4 marginTB">
              <p class="margin-none">{"ارسال تنبيه عند الأقتراب من ميعاد الإنتهاء"|gettext}</p> 
            </div>
          </div>
        </div>
      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
