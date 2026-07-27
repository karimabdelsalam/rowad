<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"اسم الوحدة"|gettext}</label>
      <div class="col-md-6">
        <input type="text" name="title" value="{$smarty.get.title}" class="form-control" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"العقار"|gettext}</label>
      <div class="col-md-5">
        <select name="locid" class="ajaxSelect" style="height: 34px;width:80%">
            <option value="">{"اختر العقار"|gettext}</option>
            {foreach $locations as $location}
              <option value="{$location.id}" {if $smarty.get.locid eq $location.id}selected="selected"{/if}>{$location.location}</option>
            {/foreach}
        </select>
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"النوع"|gettext}</label>
      <div class="col-md-2">
        <select name="type" class="form-control">
          <option value="">{"اختر نوع العقار"|gettext}</option>
          <option value="rent" {if $smarty.get.type eq "rent"}selected="selected"{/if}>{"إيجار"|gettext}</option>
          <option value="sale" {if $smarty.get.type eq "sale"}selected="selected"{/if}>{"بيع"|gettext}</option>
        </select>
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"الوضع"|gettext}</label>
      <div class="col-md-2">
        <select name="available" class="form-control">
          <option value="0">{"اختر وضع العقار"|gettext}</option>
          <option value="1" {if $smarty.get.available eq 1}selected="selected"{/if}>{"شاغر"|gettext}</option>
          <option value="off" {if $smarty.get.available eq "off"}selected="selected"{/if}>{"غير متاح"|gettext}</option>
        </select>
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"الحالة"|gettext}</label>
      <div class="col-md-2">
        <select name="status" class="form-control">
          <option value="0">{"اختر حالة العقار"|gettext}</option>
          <option value="1" {if $smarty.get.status eq 1}selected="selected"{/if}>{"نشط"|gettext}</option>
          <option value="off" {if $smarty.get.status eq "off"}selected="selected"{/if}>{"غير نشط"|gettext}</option>
        </select>
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"تاريخ الإنشاء"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder="{"من"|gettext}" />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"إلي"|gettext}" />
      </div>
    </div>
    <div class="row col-md-12">
      <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث وتنقيح"|gettext}</button>
      <button type="button" class="btn btn-default moreSearchOptions"><i class="fa fa-sort-desc"></i></button>
    </div>
    <div class="clearfix"></div>
  </form>
</div>

<div class="widget widget-body-white">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <form action="{$CPURL}/{$cumodule.name}/" method="get">
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
        <thead>
          <tr>
            <th class="center">{"م"|gettext}</th>
            <th class="center"></th>
            <th class="center">{"اسم الوحدة"|gettext}</th>
            <th class="center">{"العقار"|gettext}</th>
            <th class="center">{"الموقع"|gettext}</th>
            <th class="center">{"المالك"|gettext}</th>
            <th class="center">{"النوع"|gettext}</th>
            <th class="center">{"الحالة"|gettext}</th>
            <th class="center">{"الوضع"|gettext}</th>
            <th class="center" style="width: 220px;"></th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$results item=result}
            <tr class="selectable">
              <td class="center">{$result.id}</td>
              <td class="center"><button type="button" onclick="$('.buildInfo_{$result.id}').toggleClass('hide2')" class="btn btn-default"><i class="fa fa-wpforms"></i></button></td>
              <td>
                <strong>{$result.title}</strong>
              </td>
              <td class="center">{$result.m_location}</td>
              <td class="center"><a href="https://www.google.com/maps?q=loc:{$result.location.latitude},{$result.location.longitude}" target="_blank"><i class="fa fa-2x fa-compass"></i></a></td>
              <td class="">
                {section name=op loop=count($result.owner.id)}
                <span class="label label-info marginTB" title="{$result.owner.name[op]}" data-toggle="tooltip">
                    {$result.ownerinfo[op].fname} {$result.ownerinfo[op].fathname}
                </span><br />
                {/section}
              </td>
              <td class="center">{if $result.type eq "sale"}<span class="label label-default">{"بيع"|gettext}</span>{else}<span class="label label-warning">{"ايجار"|gettext}</span>{/if}</td>
              <td class="center">{if $result.active eq 1}<span class="label label-success">{"نشط"|gettext}</span>{else}<span class="label label-danger">{"غير نشط"|gettext}</span>{/if}</td>
              <td class="center">
                {if !empty($result.freein)}
                  <span class="label label-danger">{"غير متاح حتي"|gettext} {$result.freein|ardate:false:false}</span>
                {elseif $result.available eq 1}
                  <span class="label label-success">{"شاغر"|gettext}</span>
                {elseif $result.available eq 0 AND $result.type eq "sale"}
                  <span class="label label-danger">{"تم بيعه"|gettext}</span>
                {else}
                  <span class="label label-success">{"شاغر"|gettext}</span>
                {/if}
              </td>
              <td class="text-left center no-print">
                {include file="core/templates/module_actions.tpl" module=$cumodule.name}
              </td>
            </tr>
            {if !empty($result.nextpayment)}
            <tr class="buildContractsInfo buildInfo_{$result.id} ">
              <td colspan="20" style="padding-right: 9px!important;">
                <div class="row">
                  <div class="col-md-12">
                    <div class="col-md-3">
                      <table>
                        <tr>
                          <th>{"الدفعة القادمة"|gettext}</th>
                          {if !empty($result.nextpayment)}
                          <td>{$result.nextpayment.amount} {$config.currency}</td>
                          {else}
                          <td>{"خالص"|gettext}</td>
                          {/if}
                        </tr>
                        <tr>
                          <th>{"في تاريخ"|gettext}</th>
                          <td>{$result.nextpayment.paydate}</td>
                        </tr>
                        <tr>
                          <th>{"متبقي منها"|gettext}</th>
                          <td>{$result.nextpayment.amount-$result.nextpayment.paidamount} {$config.currency}</td>
                        </tr>
                      </table>
                    </div>
                    <div class="col-md-3">
                      <table>
                        <tr>
                          <th>{"إجمالي المستحقات"|gettext}</th>
                          <td>{$result.contract_info.alltotal} {$config.currency}</td>
                        </tr>
                        <tr>
                          <th>{"إجمالي ما دفع"|gettext}</th>
                          <td>{$result.contract_info.allpaid} {$config.currency}</td>
                        </tr>
                        <tr>
                          <th>{"متبقي دفع"|gettext}</th>
                          <td>{$result.contract_info.remain} {$config.currency} <i class="fa fa-info-circle" title="{$result.contract_info.remain_percent}%" data-toggle="tooltip"></i></td>
                        </tr>
                      </table>
                    </div>
                    <div class="{if $result.type eq "rent"}col-md-3{else}col-md-4{/if}">
                      <table>
                        <tr>
                          <th>{if $result.type eq "rent"}{"المستأجر الحالي"|gettext}{else}{"المشتري"|gettext}{/if}</th>
                          <td>
                          {if $result.type eq "rent"}
                              {$result.contract_info.buyer.profile[0].fullname}
                              <i title="{$result.contract_info.buyer.name[0]}" data-toggle="tooltip" class="fa fa-info-circle"></i>
                          {else}
                              <div class="trimHeight">
                              {section name=op loop=count($result.contract_info.buyer.id)}
                                  <p>
                                      {$result.contract_info.buyer.profile[op].fullname}
                                    <i title="{$result.contract_info.buyer.name[op]}" data-toggle="tooltip" class="fa fa-info-circle"></i>
                                  </p>
                              {/section}
                              </div>
                          {/if}
                          </td>
                        </tr>
                        <tr>
                          <th>{"رقم الجوال"|gettext}</th>
                          <td>{$result.contract_info.buyer.profile[0].mobile}</td>
                        </tr>
                        {if $result.type eq "rent"}
                        <tr>
                          <th>{"قيمة الإيجار"|gettext}</th>
                          <td>{$result.contract_info.rentvalue} {$config.currency}</td>
                        </tr>
                        {else}
                        <tr>
                          <th>{"طريقة الدفع"|gettext}</th>
                          <td>{$result.contract_info.paytypename}</td>
                        </tr>
                        {/if}
                      </table>
                    </div>
                    {if $result.type eq "rent"}
                    <div class="col-md-3">
                      <table>
                        <tr>
                          <th>{"مدة العقد"|gettext}</th>
                          <td>{$result.contract_info.periodnum} {$result.contract_info.period_title}</td>
                        </tr>
                        <tr>
                          <th>{"ينتهي في"|gettext}</th>
                          <td>{$result.contract_info.enddate|ardate:false:false}</td>
                        </tr>
                        <tr>
                          <th>{"دورة الدفع"|gettext}</th>
                          <td>{$result.contract_info.payment_title}</td>
                        </tr>
                      </table>
                    </div>
                    {/if}
                  </div>
                </div>
                {if $result.type eq "rent"}
                <div class="row no-print">
                  <div style="max-width: 70vw;overflow-x: auto;padding-top: 10px;width: 900px;text-align: center;margin: auto">
                    <div class="btn-group btn-group-xs pull-left" style="margin-bottom: 5px;z-index: 2;">
                      <button type="button" class="btn btn-default timelineScaleTo" data-scale="day">{"يوم"|gettext}</button>
                      <button type="button" class="btn btn-default timelineScaleTo" data-scale="month">{"شهر"|gettext}</button>
                      <button type="button" class="btn btn-default timelineScaleTo" data-scale="year">{"عام"|gettext}</button>
                      <button type="button" class="btn btn-default timelineScaleTo" data-scale="zoomout"><i class="fa fa-search-minus"></i></button>
                      <button type="button" class="btn btn-default timelineScaleTo" data-scale="zoomin"><i class="fa fa-search-plus"></i></button>
                    </div>
                    <div class="horzTimeline">
                      <ul class="timeline-events">
                          {foreach $result.contracts_timeline as $contract_date}
                            <li data-timeline-node="{ start:'{$contract_date.start} 00:00', end:'{$contract_date.end} 00:00', bgColor:'#8bbf61', color:'#fff', label:'{$contract_date.start} - {$contract_date.end}' }"></li>
                          {/foreach}
                      </ul>
                    </div>
                  </div>
                </div>
                {/if}
              </td>
            </tr>
            {else}
            <tr class="buildInfo_{$result.id} ">
              <td colspan="20">
                <div class="row">
                  <div class="col-md-12"></div>
                </div>
              </td>
            </tr>
            {/if}
          {foreachelse}
            <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
          {/foreach}
        </tbody>
      </table>
      <div class="pull-left checkboxs_actions hide-2">
        {include file="core/templates/module_multi_actions.tpl"}
      </div>
      {include file="core/templates/paging.tpl"}
      <div class="clearfix"></div>
    </div>
  </form>
</div>
