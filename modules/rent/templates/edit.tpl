<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="tempid" value="{$tempid}" />
  <input type="hidden" name="id" value="{$data.id}" />
  <input type="hidden" name="endedContract" value="{$data.ended}" />
  <input type="hidden" name="buildTax" value="{$data.tax_percent|default:$build.taxes}" />
  <input type="hidden" name="buildid" value="{$data.buildid|default:$smarty.get.id}" />
  {if empty($data.id)}
  <input type="hidden" name="build_rent" value="{$build.rentvalue}" />
  <input type="hidden" name="build_rent_month" value="{$build.rent_month}" />
  <input type="hidden" name="build_rent_day" value="{$build.rent_day}" />
  {/if}
  <input type="hidden" name="dimremoved" value="0" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-9">
          <div class="form-group no-border-space">
            <label class="col-md-2 control-label">{"اسم العقار"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" value="#{$build.id} - {$build.title|clean}" type="text" readonly />
            </div>
          </div>
          <div class="form-group no-border-space">
            <label class="col-md-2 control-label">{"وصف العقار"|gettext}</label>
            <div class="col-md-10">
              <textarea class="form-control" rows="10" readonly>{$build.details|clean}</textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-users"></i> {"مُلاك العقار (طرف أول)"|gettext}</h4>
          <div class="row innerAll">
            <div class="col-md-9">
              {section name=op loop=count($build.owner.id)}
              <div class="form-group no-border-space">
                <div class="col-md-5 col-md-offset-4">
                  <input class="form-control" value="{$build.owner.name[op]}" type="text" readonly />
                </div>
                <div class="col-md-2 input-group">
                  <input class="form-control" value="{$build.owner.share[op]}" type="number" readonly />
                  <span class="input-group-addon"><i class="fa fa-percent"></i></span>
                </div>
              </div>
              {/section}
            </div>
          </div>
      </div>
      <div class="row innerLR">
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-users"></i> {"المستأجر (طرف ثاني)"|gettext}</h4>
          <div class="row innerAll">
            <div class="col-md-12 buyerAjaxHolder">
              <div class="form-group hang-form">
                <label class="col-md-offset-2 col-md-2 control-label">{"اسم المستأجر"|gettext}</label>
                <div class="col-md-8">
                  <select class="buyerAjaxSearch noSelect2" style="height: 34px;width:50%"></select>
                  <a href="{$CPURL}/buyer/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i> {"اضافة مستأجر جديد"|gettext}</a>
                </div>
              </div>
              {section name=op loop=count($data.buyer.id)+1}
              <div class="form-group {if $smarty.section.op.last}buyerAjaxTemplate hide2{else}buyerAjaxCloned{/if}">
                <div class="col-md-1 col-md-offset-3">
                  <button type="button" class="btn btn-default" onclick="$(this).closest('.form-group').remove()"><i class="fa fa-minus-circle"></i></button>
                </div>
                <div class="col-md-5">
                  <input name="buyer[id][]" value="{$data.buyer.id[op]}" type="hidden" />
                  <input class="form-control" name="buyer[name][]" value="{$data.buyer.name[op]}" type="text" readonly />
                </div>
              </div>
              {/section}
            </div>
          </div>
      </div>
      {if !empty($nextpayment)}
        <div class="row innerLR">
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-info-circle"></i> {"تفاصيل الدفعة القادمة"|gettext}</h4>
          <div class="row innerAll">
            <div class="col-md-6">
              <div class="form-group">
                <label class="col-md-3 control-label">{"تاريخ الاستحقاق القادم"|gettext}</label>
                <div class="col-md-5 input-group">
                  <input class="form-control" value="{$nextpayment.paydate|clean}" type="text" readonly />
                  <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"الدفعة المستحقة القادمة"|gettext}</label>
                <div class="col-md-5 input-group">
                  <input class="form-control" value="{$nextpayment.amount|clean}" type="text" readonly />
                  <span class="input-group-addon">{$config.currency}</span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="col-md-3 control-label">{"المدفوع"|gettext}</label>
                <div class="col-md-5 input-group">
                  <input class="form-control" value="{$nextpayment.paidamount}" type="text" readonly />
                  <span class="input-group-addon">{$config.currency}</span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"المبلغ المتبقي"|gettext}</label>
                <div class="col-md-5 input-group">
                  <input class="form-control" value="{$nextpayment.amount-$nextpayment.paidamount}" type="text" readonly />
                  <span class="input-group-addon">{$config.currency}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      {/if}
      <div class="row innerLR">
        <h4 class="innerAll bg-container margin-none"><i class="fa fa-tachometer"></i> {"قراءة عدادات المرافق"|gettext}</h4>
        <div class="row innerAll">
          <div class="col-md-10 col-md-offset-1">
          {section name=op loop=count($build.meters.no)}
              <div class="form-group">
                <div class="col-md-2 pull-left">
                  <input class="form-control" value="{$build.meters.type_name[op]|clean}" type="text" readonly />
                </div>
                <div class="col-md-2 pull-left">
                  <input class="form-control" value="{$build.meters.owner_type_name[op]|clean}" type="text" readonly />
                </div>
                <div class="col-md-4 pull-left">
                  <input class="form-control" value="{$build.meters.no[op]|clean}" type="text" readonly />
                </div>
                <div class="col-md-4 pull-left">
                  <input class="form-control" name="meters[{$build.meters.no[op]|clean}]" value="{$data.meters[$build.meters.no[op]]}" type="text" placeholder="{"القراءة الحالية"|gettext}" required />
                </div>
              </div>
          {/section}
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <h4 class="innerAll bg-container margin-none"><i class="fa fa-info-circle"></i> {"تفاصيل التعاقد"|gettext} {if $data.id gt 0}<a href="#" class="remove-dim">[{"تعديل"|gettext}]</a>{/if}</h4>
        <div class="row innerAll">
          {if $data.id gt 0}<div class="contract-dim"></div>{/if}
          <div class="col-md-6">
            <div class="form-group">
              <label class="col-md-3 control-label">{"التاريخ المعتمد"|gettext}</label>
              <div class="col-md-4">
                <select name="calendar" class="form-control calendarAutoChange" required>
                  <option value="1">{"ميلادي"|gettext}</option>
                  <option value="2" {if $data.calendar eq 2 OR (empty($data.calendar) AND $config.contract_calendar eq 2)}selected="selected"{/if}>{"هجري"|gettext}</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"شروط العقد"|gettext}</label>
              <div class="col-md-8">
                <select name="rightsid" class="ajaxSelect" style="width: 80%" required>
                  {foreach $rights as $right}
                    <option value="{$right.id}" {if $data.rightsid eq $right.id}selected="selected"{/if}>{$right.title}</option>
                  {/foreach}
                </select>
                <a href="{$CPURL}/rights/add" class="btn btn-default inline-block quick-form"><i class="fa fa-plus-circle"></i></a>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"طريقة التأجير"|gettext}</label>
              <div class="col-md-4">
                <select name="period" class="form-control" required>
                  <option value="day" {if $data.period eq "day" OR (empty($data.period) AND $build.period eq "day")}selected="selected"{/if}>{"يومي"|gettext}</option>
                  <option value="year" {if $data.period eq "year" OR (empty($data.period) AND $build.period eq "year")}selected="selected"{/if}>{"سنوي"|gettext}</option>
                  <option value="month" {if $data.period eq "month" OR (empty($data.period) AND $build.period eq "month")}selected="selected"{/if}>{"شهري"|gettext}</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"مدة العقد"|gettext}</label>
              <div class="col-md-3 input-group">
                <input class="form-control" name="periodnum" value="{$data.periodnum|clean}" type="number" required />
                <span class="input-group-addon" id="periodNumStr"></span>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"تاريخ بداية العقد"|gettext}</label>
              <div class="col-md-5">
                <input class="form-control datepicker" autocomplete="off" name="startdate" value="{$data.startdate|clean}" type="text" required />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"الدفعة القادمة"|gettext}</label>
              <div class="col-md-4 input-group pull-left">
                <input class="form-control" name="nextpaymentmoney" value="{$data.nextpaymentmoney|clean}" type="number" required />
                <span class="input-group-addon">{$config.currency}</span>
              </div>
            </div>
            <div class="form-group group-addon-select">
              <label class="col-md-3 control-label">{"قيمة العمولة"|gettext}</label>
              <div class="col-md-6 input-group pull-left">
                <input class="form-control" name="commission" value="{$data.commission|default:$build.rentcomm}" type="number" />
                <span class="input-group-addon">
                  <select name="comm_cycle" class="noSelect2">
                    <option value="">{"دورة التحصيل"|gettext}</option>
                    <option value="once" {if $data.comm_cycle eq "once" OR (empty($data.payment) AND $build.payment eq "once")}selected="selected"{/if}>{"تُحصل مرة واحدة"|gettext}</option>
                    <option value="day" {if $data.comm_cycle eq "day" OR (empty($data.payment) AND $build.payment eq "day")}selected="selected"{/if}>{"تُحصل يومياً"|gettext}</option>
                    <option value="month" {if $data.comm_cycle eq "month" OR (empty($data.payment) AND $build.payment eq "month")}selected="selected"{/if}>{"تُحصل شهرياً"|gettext}</option>
                    <option value="quartyear" {if $data.comm_cycle eq "quartyear" OR (empty($data.payment) AND $build.payment eq "quartyear")}selected="selected"{/if}>{"تُحصل ربع سنوياً"|gettext}</option>
                    <option value="midyear" {if $data.comm_cycle eq "midyear" OR (empty($data.payment) AND $build.payment eq "midyear")}selected="selected"{/if}>{"تُحصل نصف سنوياً"|gettext}</option>
                    <option value="year" {if $data.comm_cycle eq "year" OR (empty($data.payment) AND $build.payment eq "year")}selected="selected"{/if}>{"تُحصل سنوياً"|gettext}</option>
                  </select>
                </span>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="col-md-3 control-label">{"تاريخ تحرير العقد"|gettext}</label>
              <div class="col-md-5">
                <input class="form-control datepicker" autocomplete="off" name="postdate" value="{$data.postdate|clean}" type="text" required />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"نوع النشاط"|gettext}</label>
              <div class="col-md-5">
                {if $build.tax eq "habit"}
                  <input class="form-control" name="usein" value="{if empty($data.usein)}{"سكني"|gettext}{else}{$data.usein|clean}{/if}" type="text" required />
                {else}
                  <input class="form-control" name="usein" value="{$data.usein|clean}" type="text" placeholder="{"نوع النشاط التجاري"|gettext}" required />
                {/if}
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"طريقة دفع الإيجار"|gettext}</label>
              <div class="col-md-4">
                <select name="payment" class="form-control" required>
                    <option value="day">{"إيجار يومي"|gettext}</option>
                    <option value="month" {if $data.payment eq "month" OR (empty($data.payment) AND $build.payment eq "month")}selected="selected"{/if}>{"إيجار شهري"|gettext}</option>
                    <option value="quartyear" {if $data.payment eq "quartyear" OR (empty($data.payment) AND $build.payment eq "quartyear")}selected="selected"{/if}>{"دفع ربع سنوي"|gettext}</option>
                    <option value="midyear" {if $data.payment eq "midyear" OR (empty($data.payment) AND $build.payment eq "midyear")}selected="selected"{/if}>{"دفع نصف سنوي"|gettext}</option>
                    <option value="year" {if $data.payment eq "year" OR (empty($data.payment) AND $build.payment eq "year")}selected="selected"{/if}>{"دفع سنوي"|gettext}</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"الإيجار"|gettext} <span id="periodRentStr"></span></label>
              <div class="col-md-5 input-group">
                <input class="form-control" name="yearlyrent" value="{if $data.id gt 0}{$data.yearlyrent}{else}{$build.rentvalue}{/if}" type="number" required />
                <span class="input-group-addon">{$config.currency}</span>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"تاريخ نهاية العقد"|gettext}</label>
              <div class="col-md-5">
                <input class="form-control datepicker" autocomplete="off" name="enddate" value="{$data.enddate|clean}" type="text" required readonly />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label shrink">{"تاريخ الاستحقاق القادم"|gettext}</label>
              <div class="col-md-5">
                <input class="form-control" name="nextpaymentdate" value="{$data.nextpaymentdate|clean}" type="text" required readonly />
              </div>
            </div>
            <div class="form-group no-border-space">
              <label class="col-md-3 control-label">{"قيمة الدفعات"|gettext}</label>
              <div class="col-md-3 input-group pull-left">
                <input class="form-control" name="rent_without_tax" value="{$build.rentvalue}" type="number" required />
                <span class="input-group-addon"><i class="fa fa-money"></i></span>
              </div>
              <div class="pull-left" style="padding: 8px 10px 0 0"><i class="fa fa-plus"></i></div>
              <div class="col-md-3 input-group pull-left">
                <input class="form-control" name="tax" type="number" required readonly />
                <span class="input-group-addon"><i class="fa fa-hospital-o" title="{"ضريبه"|gettext}"></i></span>
              </div>
            </div>
            <div class="form-group no-border-space">
              <div class="col-md-offset-3 col-md-3 input-group pull-left">
                <input class="form-control" name="rentvalue" type="number" required readonly />
                <span class="input-group-addon"><i class="fa fa-calculator" title="{"مجموع"|gettext}"></i></span>
              </div>
              <div class="pull-left" style="padding: 8px 10px 0 0">=</div>
              <div class="col-md-5 input-group pull-left">
                <input class="form-control" name="alltotal" type="text" readonly />
                <span class="input-group-addon">{"إجمالي قيمة العقد"|gettext}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <div class="col-md-9">
          <div class="form-group no-border-space">
            <label class="col-md-2 control-label">{"ملاحظات"|gettext}</label>
            <div class="col-md-10">
                <textarea class="form-control" name="notes" rows="10">{$data.notes|clean}</textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-gavel"></i> {"شروط اضافية"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default" id="addNewRights"><i class="fa fa-plus-circle"></i></button></div></h4>
          <div class="row innerAll">
            <div class="col-md-10 col-md-offset-1 extraRightsHolder">
              {if empty($data.extrarights)}
              <div class="alert alert-info text-center">{"يمكنك اضافة عدد من الشروط الأضافية بالضفط على"|gettext}  <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default"><i class="fa fa-plus-circle"></i></button></div> {"بالشريط العلوي"|gettext}</div>
              {/if}
              {section name=op loop=count($data.extrarights)+1}
              <div class="form-group {if $smarty.section.op.last}extraRightsTemplate hide2{else}extraRightsCloned{/if}">
                <div class="col-md-11">
                  <input class="form-control" name="extrarights[]" value="{$data.extrarights[op]}" type="text" required />
                </div>
                <div class="col-md-1">
                  <button type="button" class="btn btn-default" onclick="$(this).closest('.form-group').remove()"><i class="fa fa-minus-circle"></i></button>
                </div>
              </div>
              {/section}
           </div>
        </div>
      </div>
      <div class="row innerLR">
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-upload"></i> {"إرفاق مستندات"|gettext} </h4>
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
