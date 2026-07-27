<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <input type="hidden" name="calendar" value="{$data.calendar|default:$config.salary_calendar}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم الموظف"|gettext}</label>
            <div class="col-md-4">
              <select class="employeeAjaxSearch noSelect2" name="employeeid" style="width:100%" required>
                <option value="{$data.employeeid}">{$data.fname} {$data.fathname} {$data.lname} {$data.famname}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"تاريخ الحسم"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control datepicker" autocomplete="off" name="createddate" value="{$data.createddate|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"مبلغ الحسم"|gettext}</label>
            <div class="col-md-2 input-group">
              <input class="form-control" name="amount" value="{$data.amount|clean}" type="number" step="any" required />
              <span class="input-group-addon">{$config.currency}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"سبب الحسم"|gettext}</label>
            <div class="col-md-3">
              <select name="reason" class="form-control" required>
                <option value="1" {if $data.reason eq "1"}selected="selected"{/if}>{"تأخير حضور"|gettext}</option>
                <option value="2" {if $data.reason eq "2"}selected="selected"{/if}>{"غياب"|gettext}</option>
                <option value="3" {if $data.reason eq "3"}selected="selected"{/if}>{"سوء سلوك"|gettext}</option>
                <option value="4" {if $data.reason eq "4"}selected="selected"{/if}>{"أخري"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group otherReasonField">
            <label class="col-md-2 control-label">{"سبب أخر"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="reasontext" value="{$data.reasontext|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"يخصم من شهر"|gettext}</label>
            <div class="col-md-2">
              <select name="formonth" class="form-control" required>
                {foreach name=op from=$dates.months item=month}
                <option value="{$smarty.foreach.op.iteration}" {if $data.formonth eq $smarty.foreach.op.iteration}selected="selected"{/if}>{$month}</option>
                {/foreach}
              </select>
            </div>
            <div class="col-md-2">
              <select name="foryear" class="form-control" required>
                {section name=op loop=$dates.end start=$dates.start}
                <option value="{$smarty.section.op.index}" {if $data.foryear eq $smarty.section.op.iteration}selected="selected"{/if}>{$smarty.section.op.index}</option>
                {/section}
              </select>
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
