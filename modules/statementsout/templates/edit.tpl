<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <input type="hidden" name="tempid" value="{$tempid}" />
  <input type="hidden" name="transids" value="{$smarty.get.transid}" />
  <input type="hidden" name="transid" value="{$smarty.get.transid}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"تاريخ الصرف"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control datepicker" autocomplete="off" name="issueddate" value="{$data.issueddate|clean}" type="text" required />
            </div>
            <label class="col-md-2 col-md-offset-1 control-label">{"التاريخ المعتمد"|gettext}</label>
            <div class="col-md-3">
              <select name="calendar" class="form-control calendarAutoChange" required>
                <option value="1">{"ميلادي"|gettext}</option>
                <option value="2" {if $data.calendar eq 2 OR (empty($data.calendar) AND $config.debit_calendar eq 2)}selected="selected"{/if}>{"هجري"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"المبلغ"|gettext}</label>
            <div class="col-md-2 input-group">
              {if !empty($data.build) AND !empty($data.id)}
              <span class="label label-primary">{$data.amount} {$config.currency}</span>
              {else}
              <input class="form-control" name="amount" value="{$data.amount|default:$smarty.get.amount}" type="number" step="any" required />
              <span class="input-group-addon">{$config.currency}</span>
              {/if}
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"يخصم من"|gettext} {"خصم المبلغ من رصيد الحساب البنكي"|gettext|help}</label>
            <div class="col-md-6">
              <select class="form-control" name="accid">
                <option value="0"></option>
                {foreach $bankaccounts as $bankaccount}
                <option value="{$bankaccount.id}" {if (empty($data.accid) AND $bankaccount.main eq 1) OR $data.accid eq $bankaccount.id}selected="selected"{/if}>{$bankaccount.bank}: {$bankaccount.fullname}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group no-border-space">
            <label class="col-md-2 control-label">{"يصرف إلي"|gettext}</label>
            <div class="col-md-3">
              <select name="from_type" class="form-control" required>
                {foreach $methods as $method}
                <option value="{$method.name}" {if $data.from_type eq $method.name}selected="selected"{/if}>{$method.title|gettext}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group StatementFromType_owner">
            <label class="col-md-2 control-label">{"اسم المالك"|gettext}</label>
            <div class="col-md-6">
              <select class="ajaxSelect" name="ownerid[]" style="width:100%" multiple required>
                {foreach $companyowners as $companyowner}
                <option value="{$companyowner.id}" {if $data.from_type eq "owner" AND $companyowner.id|in_array:$data.from_id}selected="selected"{/if}>{$companyowner.fname} {$companyowner.fathname} {$companyowner.lname} {$companyowner.famname}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group StatementFromType_employee">
            <label class="col-md-2 control-label">{"اسم الموظف"|gettext}</label>
            <div class="col-md-4">
              <select class="employeeAjaxSearch noSelect2" name="employeeid" style="width:100%" required>
                {if $data.from_type eq "employee"}<option value="{$data.from_id}">{$data.from.fname} {$data.from.fathname} {$data.from.lname} {$data.from.famname}</option>{/if}
              </select>
            </div>
            <div class="col-md-2">
              <a href="{$CPURL}/employees/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group StatementFromType_buildowner">
            <label class="col-md-2 control-label">{"اسم مالك العقار"|gettext}</label>
            <div class="col-md-4">
              <select class="buildownerAjaxSearch noSelect2" name="buildownerid" style="width:100%" required>
                {if $data.from_type eq "buildowner"}<option value="{$data.from_id}">{$data.from.fname} {$data.from.fathname} {$data.from.lname} {$data.from.famname}</option>{/if}
              </select>
            </div>
            <div class="col-md-2">
              <a href="{$CPURL}/owner/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group StatementFromType_buildrenter">
            <label class="col-md-2 control-label">{"اسم المستأجر"|gettext}</label>
            <div class="col-md-4">
              <select class="buildrenterAjaxSearch noSelect2" name="renterid" style="width:100%" required>
                {if $data.from_type eq "buildrenter"}<option value="{$data.from_id}">{$data.from.fname} {$data.from.fathname} {$data.from.lname} {$data.from.famname}</option>{/if}
              </select>
            </div>
            <div class="col-md-2">
              <a href="{$CPURL}/buyer/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group StatementFromType_buildbuyer">
            <label class="col-md-2 control-label">{"اسم المشتري"|gettext}</label>
            <div class="col-md-4">
              <select class="buildbuyerAjaxSearch noSelect2" name="buyerid" style="width:100%" required>
                {if $data.from_type eq "buildbuyer"}<option value="{$data.from_id}">{$data.from.fname} {$data.from.fathname} {$data.from.lname} {$data.from.famname}</option>{/if}
              </select>
            </div>
            <div class="col-md-2">
              <a href="{$CPURL}/buyer/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group StatementFromType_govorg">
            <label class="col-md-2 control-label">{"اسم الجهة الحكومية"|gettext}</label>
            <div class="col-md-4">
              <select class="govorgAjaxSearch noSelect2" name="govorgid" style="width:100%" required>
                {if $data.from_type eq "govorg"}<option value="{$data.from_id}">{$data.from.title}</option>{/if}
              </select>
            </div>
            <div class="col-md-2">
              <a href="{$CPURL}/govorgs/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group StatementFromType_citizen">
            <label class="col-md-2 control-label">{"اسم المواطن"|gettext}</label>
            <div class="col-md-4">
              <select class="citizenAjaxSearch noSelect2" name="citizenid" style="width:100%" required>
                {if $data.from_type eq "citizen"}<option value="{$data.from_id}">{$data.from.title}</option>{/if}
              </select>
            </div>
            <div class="col-md-2">
              <a href="{$CPURL}/citizen/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group StatementFromType_comorg">
            <label class="col-md-2 control-label">{"اسم الجهة التجارية"|gettext}</label>
            <div class="col-md-4">
              <select class="comorgAjaxSearch noSelect2" name="comorgid" style="width:100%" required>
                {if $data.from_type eq "comorg"}<option value="{$data.from_id}">{$data.from.title}</option>{/if}
              </select>
            </div>
            <div class="col-md-2">
              <a href="{$CPURL}/tradeorgs/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group {if empty($data.id)}no-border-space{/if}">
            <label class="col-md-2 control-label">{"خاص بالعقار"|gettext}</label>
            <div class="col-md-5">
              {if !empty($data.build)}
              <input type="hidden" name="buildid" value="{$data.buildid}">
              <span class="label label-primary">{$data.build}</span>
              {else}
              <select class="buildAjaxSearch noSelect2" name="buildid" style="width:100%" required>
                {if !empty($data.buildid)}
                  <option value="{$data.buildid}">{$data.build}</option>
                {/if}
              </select>
              {/if}
            </div>
          </div>
          {if empty($data.id)}
          <div class="form-group">
            <label class="col-md-2 control-label">{"بند مصروفات"|gettext} {"يضاف المبلغ كبند مصروفات على العقار ليتم خصمه لاحقاً وتحصيله من ملاك العقار او المستأجر الحالي او المشتري"|gettext|help}</label>
            <div class="col-md-1">
              <input name="buildexpenses" type="checkbox" class="ckeckonoff" />
            </div>
            <div class="col-md-3 expensesChoice">
              <select name="expensestype">
                <option value="owner">{"خصمه من ملاك العقار"|gettext}</option>
                <option value="renter">{"تحصيله من المستأجر الحالي"|gettext}</option>
                <option value="buyer">{"تحصيله من مشتري العقار"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group expensesChoice">
            <label class="col-md-2 control-label">{"تطبيق ضريبة القيمة المضافة"|gettext}</label>
            <div class="col-md-2">
              <input name="taxs" type="checkbox" class="ckeckonoff" />
            </div>
          </div>
          {/if}

          <div class="form-group">
            <label class="col-md-2 control-label">{"نوع الصرف"|gettext}</label>
            <div class="col-md-4">
              <select name="typeid" class="ajaxSelect" style="width:80%" required>
                {foreach $outtypes as $outtype}
                <option value="{$outtype.id}" {if $data.typeid eq $outtype.id}selected="selected"{/if}>{$outtype.title}</option>
                {/foreach}
              </select>
              <a href="{$CPURL}/outtypes/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>

          {if !empty($smarty.get.transid)}
            <div class="form-group">
              <label class="col-md-2 control-label"></label>
              <div class="col-md-8 paymentInfo"></div>
            </div>
          {/if}

          {if !empty($data.payments)}
            <div class="form-group">
              <label class="col-md-2 control-label">{"المستحقات المالية"|gettext} {"تفاصيل حركات المستحقات المالية المرتبطة بهذا السند"|gettext|help}</label>
              <div class="col-md-10">
                  {include file="pays_pending/templates/data_table.tpl" results=$data.payments shortcut=1}
              </div>
            </div>
          {/if}

          {if !empty($data.transactions)}
            <div class="form-group">
              <label class="col-md-2 control-label">{"الإيرادات المالية"|gettext} {"تفاصيل حركات الإيرادات المالية المرتبطة بهذا السند"|gettext|help}</label>
              <div class="col-md-10">
                  {include file="transactions/templates/data_table.tpl" results=$data.transactions shortcut=1}
              </div>
            </div>
          {/if}

          <div class="form-group">
            <label class="col-md-2 control-label">{"طريقة الإستلام"|gettext}</label>
            <div class="col-md-3">
              <select name="pay_method" class="form-control" required>
                <option value="cash" {if $data.pay_method eq "cash"}selected="selected"{/if}>{"نقداً"|gettext}</option>
                <option value="cheque" {if $data.pay_method eq "cheque"}selected="selected"{/if}>{"شيك"|gettext}</option>
                <option value="bank" {if $data.pay_method eq "bank"}selected="selected"{/if}>{"تحويل بنكي"|gettext}</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR StatementPayMethod_cheque">
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"رقم الشيك"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="cheque[no]" value="{$data.cheque.no|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"اسم البنك"|gettext}</label>
            <div class="col-md-6">
              <select class="ajaxSelect" style="width: 80%" name="cheque[bankid]" required>
                {foreach $banks as $bank}
                <option value="{$bank.id}" {if $data.cheque.bankid eq $bankaccount.id}selected="selected"{/if}>{$bank.title|clean}</option>
                {/foreach}
              </select>
              <a href="{$CPURL}/banks/add" class="btn btn-default inline-block quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"تاريخ الشيك"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control datepicker" autocomplete="off" name="cheque[issuedate]" value="{$data.cheque.issuedate|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"ارفاق صورة من الشيك"|gettext}</label>
            <div class="col-md-6 input-group">
              <input name="cheque[copy]" type="hidden" value="{$data.cheque.copy}" />
              <input class="form-control" name="cheque_copy" type="file" {if empty($data.cheque.copy)}required{/if} />
              {if !empty($data.cheque.copy)}
              <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$data.cheque.copy|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
              {/if}
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR StatementPayMethod_bank">
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"تاريخ التحويل"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control datepicker" autocomplete="off" name="bank[issuedate]" value="{$data.bank.issuedate|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"رقم الحساب المحول منه"|gettext}</label>
            <div class="col-md-7">
              <select class="form-control" name="bank[frombankid]" required>
                {foreach $bankaccounts as $bankaccount}
                <option value="{$bankaccount.id}" {if $data.bank.frombankid eq $bankaccount.id}selected="selected"{/if}>{$bankaccount.bank}: {$bankaccount.fullname}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"اسم الحساب المحول إليه"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="bank[toname]" value="{$data.bank.toname|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"ارفاق صورة من الحوالة"|gettext}</label>
            <div class="col-md-7 input-group">
              <input name="bank[copy]" type="hidden" value="{$data.bank.copy}" />
              <input class="form-control" name="bank_copy" type="file" {if empty($data.bank.copy)}required{/if} />
              {if !empty($data.bank.copy)}
              <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$data.bank.copy|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
              {/if}
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"رقم مرجع التحويل"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="bank[refno]" value="{$data.bank.refno|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"اسم البنك المحول اليه"|gettext}</label>
            <div class="col-md-7">
              <select class="ajaxSelect" style="width: 80%" name="bank[tobankid]" required>
                {foreach $banks as $bank}
                <option value="{$bank.id}" {if $data.bank.tobankid eq $bankaccount.id}selected="selected"{/if}>{$bank.title|clean}</option>
                {/foreach}
              </select>
              <a href="{$CPURL}/banks/add" class="btn btn-default inline-block quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"رقم الحساب المحول اليه"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="bank[toaccno]" value="{$data.bank.toaccno|clean}" type="text" required />
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"سبب اصدار السند"|gettext}</label>
            <div class="col-md-5">
              <textarea class="form-control" name="reason" rows="6" required>{$data.reason|clean}</textarea>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"ملاحظات"|gettext}</label>
            <div class="col-md-8">
              <textarea class="form-control" name="notes" rows="8">{$data.notes|clean}</textarea>
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
