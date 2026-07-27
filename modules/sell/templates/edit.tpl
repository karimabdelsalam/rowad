<form action="" class="form-horizontal margin-none form-ajax" method="post" onsubmit="return verifyOwnerShares()">
  <input type="hidden" name="tempid" value="{$tempid}" />
  <input type="hidden" name="id" value="{$data.id}" />
  <input type="hidden" name="buildid" value="{$data.buildid|default:$smarty.get.id}" />
  <input type="hidden" name="dimremoved" value="0" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-3 control-label">{"تاريخ تحرير العقد"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control datepicker" autocomplete="off" name="postdate" value="{$data.postdate|clean}" type="text" required />
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-3 control-label">{"التاريخ المعتمد"|gettext}</label>
            <div class="col-md-6">
              <select name="calendar" class="form-control calendarAutoChange" required>
                <option value="1">{"ميلادي"|gettext}</option>
                <option value="2" {if $data.calendar eq 2 OR (empty($data.calendar) AND $config.contract_calendar eq 2)}selected="selected"{/if}>{"هجري"|gettext}</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <div class="col-md-9">
          <div class="form-group no-border-space">
            <label class="col-md-2 control-label">{"اسم العقار"|gettext}</label>
            <div class="col-md-6">
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
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-users"></i>{" مُلاك العقار (طرف أول)"|gettext}</h4>
          <div class="row innerAll">
            <div class="col-md-10">
              {section name=op loop=count($build.owner.id)}
              <div class="form-group no-border-space">
                <div class="col-md-5 col-md-offset-2">
                  <input class="form-control" value="{$build.owner.name[op]}" type="text" readonly />
                </div>
                <div class="col-md-2 input-group pull-left">
                  <input class="form-control" value="{$build.owner.share[op]}" type="number" readonly />
                  <span class="input-group-addon"><i class="fa fa-percent"></i></span>
                </div>
                <div class="col-md-2 input-group pull-left">
                  <input class="form-control" value="{$build.owner.outgoings[op]}" type="number" readonly />
                  <span class="input-group-addon"><i class="fa fa-percent"></i></span>
                </div>
              </div>
              {/section}
            </div>
          </div>
      </div>
      <div class="row innerLR">
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-users"></i>{" المشتريين (طرف ثاني)"|gettext}</h4>
          <div class="row innerAll">
            <div class="col-md-12 buyerAjaxHolder">
              <div class="form-group hang-form">
                <label class="col-md-offset-2 col-md-2 control-label">{"اسم المشتري"|gettext}</label>
                <div class="col-md-8">
                  <select class="buyerAjaxSearch noSelect2" style="height: 34px;width:50%"></select>
                  <a href="{$CPURL}/buyer/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i> {"اضافة مشتري جديد"|gettext}</a>
                </div>
              </div>
              {section name=op loop=count($data.buyer.id)+1}
              <div class="form-group {if $smarty.section.op.last}buyerAjaxTemplate hide2{else}buyerAjaxCloned{/if}">
                <div class="col-md-1 col-md-offset-2">
                  <button type="button" class="btn btn-default" onclick="$(this).closest('.form-group').remove()"><i class="fa fa-minus-circle"></i></button>
                </div>
                <div class="col-md-5">
                  <input name="buyer[id][]" value="{$data.buyer.id[op]}" type="hidden" />
                  <input class="form-control" name="buyer[name][]" value="{$data.buyer.name[op]}" type="text" readonly />
                </div>
                <div class="col-md-2 input-group pull-left">
                  <input class="form-control" name="buyer[share][]" value="{$data.buyer.share[op]|default:''}" placeholder="{"نسبة التملك"|gettext}" type="number" min="1" max="100" step="any" required />
                  <span class="input-group-addon">%</span>
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
              <label class="col-md-3 control-label">{"شروط العقد"|gettext}</label>
              <div class="col-md-8">
                <select name="rightsid" class="fit-content" required>
                  {foreach $rights as $right}
                    <option value="{$right.id}" {if $data.rightsid eq $right.id}selected="selected"{/if}>{$right.title}</option>
                  {/foreach}
                </select>
                <a href="{$CPURL}/rights/add" class="btn btn-default inline-block quick-form"><i class="fa fa-plus-circle"></i></a>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"طريقة البيع"|gettext}</label>
              <div class="col-md-9">
                <select name="paytype" class="fit-content" required>
                  <option value="once">{"تسديد كامل المبلغ"|gettext}</option>
                  <option value="parts" {if $data.paytype eq "parts"}selected="selected"{/if}>{"تسديد المبلغ علي دفعات"|gettext}</option>
                  <option value="installment" {if $data.paytype eq "installment"}selected="selected"{/if}>{"تقسيط المبلغ"|gettext}</option>
                </select>
                <div class="Fields_installment">
                  <select name="installment[period]" class="fit-content" required>
                    <option value="">{"طريقة التقسيط"|gettext}</option>
                    <option value="month" {if $data.installment.period eq "month"}selected="selected"{/if}>{"شهري"|gettext}</option>
                    <option value="quartyear" {if $data.installment.period eq "quartyear"}selected="selected"{/if}>{"ربع سنوي"|gettext}</option>
                    <option value="midyear" {if $data.installment.period eq "midyear"}selected="selected"{/if}>{"نصف سنوي"|gettext}</option>
                    <option value="year" {if $data.installment.period eq "year"}selected="selected"{/if}>{"سنوي"|gettext}</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-group Fields_once">
              <label class="col-md-3 control-label">{"قيمة عمولة المكتب"|gettext}</label>
              <div class="col-md-5 input-group pull-left">
                <input class="form-control" name="once[comission]" value="{$data.once.comission|clean}" type="number" step="any" required />
                <span class="input-group-addon">
                  <select name="once[comissiontype]" class="noSelect2" style="border: none" required>
                    <option value="money">{$config.currency}</option>
                    <option value="percent" {if $data.once.comissiontype eq "percent"}selected="selected"{/if}>{"نسبة مئوية"|gettext}</option>
                  </select>
                </span>
              </div>
              <div class="col-md-4 pull-left" style="margin-top: 7px">
                <div class="comissionTotal hide2 inline-flex">
                  <span class="label label-primary"></span>
                </div>
                <div class="comissionTotal hide2 inline-flex">
                  <label class="label label-default">{$config.currency}</label>
                </div>
              </div>
            </div>
            <div class="form-group Fields_installment">
              <label class="col-md-3 control-label">{"عدد الأقساط"|gettext}</label>
              <div class="col-md-4">
                <input class="form-control" name="installment[installscount]" value="{$data.installment.installscount|clean}" type="number" required />
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group Fields_installment">
              <label class="col-md-3 control-label">{"تاريخ بداية الأقساط"|gettext}</label>
              <div class="col-md-6 input-group">
                <input class="form-control datepicker" autocomplete="off" name="installment[startdate]" value="{$data.installment.startdate|clean}" type="text" required />
                <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
              </div>
            </div>
            <div class="form-group Fields_once">
              <label class="col-md-3 control-label">{"نظام العمولة"|gettext}</label>
              <div class="col-md-5">
                <select name="once[comissionmethod]" class="form-control" required>
                  <option value="buyer">{"يتحمله المشتري (صافي)"|gettext}</option>
                  <option value="seller" {if $data.once.comissionmethod eq "seller"}selected="selected"{/if}>{"يتحمله البائع (كدر)"|gettext}</option>
                  <option value="fifty" {if $data.once.comissionmethod eq "fifty"}selected="selected"{/if}>{"بين البائع والمشتري (الكل)"|gettext}</option>
                </select>
              </div>
            </div>
            <div class="form-group Fields_parts">
              <label class="col-md-3 control-label">{"نظام العمولة"|gettext}</label>
              <div class="col-md-5">
                <select name="parts[comissionmethod][0]" class="form-control" required>
                  <option value="buyer">{"يتحمله المشتري (صافي)"|gettext}</option>
                  <option value="seller" {if $data.parts.comissionmethod[0] eq "seller"}selected="selected"{/if}>{"يتحمله البائع (كدر)"|gettext}</option>
                  <option value="fifty" {if $data.parts.comissionmethod[0] eq "fifty"}selected="selected"{/if}>{"بين البائع والمشتري (الكل)"|gettext}</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"اجمالي المبلغ"|gettext}</label>
              <div class="col-md-5 input-group">
                <input class="form-control" name="total" value="{$data.total|clean}" type="number" step="any" required />
                <span class="input-group-addon">{$config.currency}</span>
              </div>
            </div>
            <div class="form-group Fields_installment">
              <label class="col-md-3 control-label">{"قيمة الدفعة الأولى"|gettext}</label>
              <div class="col-md-5 input-group">
                <input class="form-control" name="installment[firstvalue]" value="{$data.installment.firstvalue|clean}" type="number" step="any" required />
                <span class="input-group-addon">{$config.currency}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR Fields_parts">
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-calendar"></i> {"عدد الدفعات"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default" id="partsAjaxAdder"><i class="fa fa-plus-circle"></i></button></div></h4>
          <div class="row innerAll">
            <div class="col-md-10 col-md-offset-1 partsAjaxHolder">
              {if empty($data.parts)}
              <div class="alert alert-info text-center">  {"يمكنك زيادة عدد الدفعات بالضغط على"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default"><i class="fa fa-plus-circle"></i></button></div>{"بالشريط العلوي"|gettext}</div>
              {/if}
              {section name=op loop=count($data.parts.total)+1}
              <div class="{if $smarty.section.op.last}partsAjaxTemplate hide2{else}partsAjaxCloned{/if}">
                <div class="row {if $config.style eq 'classic'}bg-container{else}table-light{/if} innerAll radius-corner">
                  <h4 class="center-header">{"الدفعة رقم"|gettext} <span class="partsSort">{$smarty.section.op.iteration}</span></h4>
                  <div class="separator"></div>
                  <div class="col-md-12">
                    <button type="button" class="btn btn-default pull-right" onclick="$(this).closest('div.partsAjaxCloned').remove();sortParts()"><i class="fa fa-minus-circle"></i></button>
                    <div class="form-group">
                      <label class="col-md-2 control-label">{"المبلغ"|gettext}</label>
                      <div class="col-md-3 input-group pull-left">
                        <input class="form-control" name="parts[total][]" onchange="partsCommChange(this)" value="{$data.parts.total[op]}" type="number" step="any" placeholder="{"المبلغ"|gettext}" required />
                        <span class="input-group-addon">{$config.currency}</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-2 control-label">{"طريقة الدفع"|gettext}</label>
                      <div class="col-md-4">
                        <select name="parts[paytype][]" class="form-control" required onchange="partsPayment(this)">
                          <option value="">{"اختر طريقة الدفع"|gettext}</option>
                          <option value="cheque" {if $data.parts.paytype[op] eq "cheque"}selected="selected"{/if}>{"شيك"|gettext}</option>
                          <option value="cash" {if $data.parts.paytype[op] eq "cash"}selected="selected"{/if}>{"نقداً"|gettext}</option>
                        </select>
                      </div>
                    </div>
                    <div class="form-group partsPaymentcheque hide2">
                      <label class="col-md-2 control-label">{"بيانات الشيك"|gettext}</label>
                      <div class="col-md-4">
                        <input class="form-control" name="parts[cheque_no][]" value="{$data.parts.cheque_no[op]}" type="text" placeholder="{"رقم الشيك"|gettext}" required />
                      </div>
                      <div class="col-md-3 input-group pull-left">
                        <input class="form-control datepicker" autocomplete="off" name="parts[cheque_date][]" value="{$data.parts.cheque_date[op]}" type="text" placeholder="{"تاريخ الشيك"|gettext}" required />
                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                      </div>
                      <div class="col-md-3">
                        <input class="form-control" name="parts[cheque_bank][]" value="{$data.parts.cheque_bank[op]}" type="text" placeholder="{"اسم البنك"|gettext}" required />
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-2 control-label">{"عمولة المكتب"|gettext}</label>
                      <div class="col-md-3 input-group pull-left">
                        <input class="form-control" name="parts[cash_salecomm][]" value="{$data.parts.cash_salecomm[op]|default:$build.salecomm}" onchange="partsCommChange(this)" type="number" step="any" placeholder="{"النسبة المئوية"|gettext}" required />
                        <span class="input-group-addon"><i class="fa fa-percent"></i></span>
                      </div>
                      <div class="col-md-4 input-group pull-left">
                        <input class="form-control" name="parts[cash_commvalue][]" value="{$data.parts.cash_commvalue[op]}" type="number" step="any" placeholder="{"قسمة عمولة المكتب"|gettext}" required />
                        <span class="input-group-addon">{$config.currency}</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-1 col-md-offset-7 control-label autoCheckInputs">
                        <input name="parts[cheque_notnow][]" value="{$data.parts.cheque_notnow[op]}" type="hidden" />
                        <input onchange="chequeNotnowField(this)" class="autoCheckInputsEvent" type="checkbox" {if $data.parts.cheque_notnow[op] eq 1}checked="checked"{/if} /> {"مؤجلة"|gettext}
                      </label>
                      <div class="col-md-4 input-group chequeNotnowEnabled hide2">
                        <input class="form-control datepicker" autocomplete="off" name="parts[cheque_todate][]" value="{$data.parts.cheque_todate[op]}" type="text" placeholder="{"بتاريخ"|gettext}" required />
                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                      </div>
                      <div class="col-md-4 input-group chequeNotnowDisabled">
                        <input class="form-control datepicker" autocomplete="off" name="parts[cheque_recdate][]" value="{$data.parts.cheque_recdate[op]}" type="text" placeholder="{"تم استلام مبلغ الدفعة اعلاه بتاريخ"|gettext}" required />
                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              {/section}
            </div>
          </div>
      </div>
      <div class="row innerLR">
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-gavel"></i> {"شروط اضافية"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default" id="addNewRights"><i class="fa fa-plus-circle"></i></button></div></h4>
          <div class="row innerAll">
            <div class="col-md-10 col-md-offset-1 extraRightsHolder">
              {if empty($data.extrarights)}
              <div class="alert alert-info text-center">{"يمكنك اضافة عدد من الشروط الأضافية بالضفط على"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default"><i class="fa fa-plus-circle"></i></button></div> {"بالشريط العلوي"|gettext}</div>
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
