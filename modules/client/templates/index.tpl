<div class="row" style="margin-bottom: 20px">
  <div class="col-md-12 text-center">
    <div style="padding: 30px;background: #fff;margin-bottom: 20px;margin-top:4px;border-radius: 5px;">
      <img src="{$MEDIAURL}/{$config.logo}" class="no-border" alt="" height="150">
      <p style="font-size:20px;margin-top:8px">{$config.sitename}</p>
      <p style="font-size:18px">{$config.en_sitename}</p>
    </div>
  </div>
</div>

<div class="filter-bar">
  <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseBuildContainer"><i class="fa fa-minus-square-o"></i> {"بيانات العقار"|gettext}</h4>
  <div class="separator"></div>
  <div class="innerAll collapseBuildContainer">
  {if $person eq "owner"}
      {include file="core/templates/build_info.tpl" quick=1}
  {else}
      {include file="core/templates/build_info.tpl" quick=1 hide_owners_info=1}
  {/if}
  </div>
</div>

<div class="filter-bar">
  <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseBuildContainer2"><i class="fa fa-minus-square-o"></i> {"بيانات العقد"|gettext}</h4>
  <div class="separator"></div>
  <div class="innerAll collapseBuildContainer2">
    <table class="table table-condensed table-striped table-primary table-vertical-center">
      <tr class="buildContractsInfo">
        <td colspan="20" style="padding-right: 9px!important;">
          <div class="row">
            <div class="col-md-12">
              <div class="col-md-3">
                <table>
                  <tr>
                    <th>{"الدفعة القادمة"|gettext}</th>
                      {if !empty($contract.nextpayment)}
                        <td>{$contract.nextpayment.amount} {$config.currency}</td>
                      {else}
                        <td>{"خالص"|gettext}</td>
                      {/if}
                  </tr>
                  <tr>
                    <th>{"في تاريخ"|gettext}</th>
                    <td>{$contract.nextpayment.paydate}</td>
                  </tr>
                  <tr>
                    <th>{"متبقي منها"|gettext}</th>
                    <td>{$contract.nextpayment.amount-$contract.nextpayment.paidamount} {$config.currency}</td>
                  </tr>
                </table>
              </div>
              <div class="col-md-3">
                <table>
                  <tr>
                    <th>{"إجمالي المستحقات"|gettext}</th>
                    <td>{$contract.alltotal} {$config.currency}</td>
                  </tr>
                  <tr>
                    <th>{"إجمالي ما دفع"|gettext}</th>
                    <td>{$contract.allpaid} {$config.currency}</td>
                  </tr>
                  <tr>
                    <th>{"متبقي دفع"|gettext}</th>
                    <td>{$contract.remain} {$config.currency} <i class="fa fa-info-circle" title="{$contract.remain_percent}%" data-toggle="tooltip"></i></td>
                  </tr>
                </table>
              </div>
              <div class="{if $type eq "rent"}col-md-3{else}col-md-4{/if}">
                <table>
                  <tr>
                    <th>{if $type eq "rent"}{"المستأجر الحالي"|gettext}{else}{"المشتري"|gettext}{/if}</th>
                    <td>
                        {if $type eq "rent"}
                            {$contract.buyer.profile[0].fullname}
                          <i title="{$contract.buyer.name[0]}" data-toggle="tooltip" class="fa fa-info-circle"></i>
                        {else}
                          <div class="trimHeight">
                              {section name=op loop=count($contract.buyer.id)}
                                <p>
                                    {$contract.buyer.profile[op].fullname}
                                  <i title="{$contract.buyer.name[op]}" data-toggle="tooltip" class="fa fa-info-circle"></i>
                                </p>
                              {/section}
                          </div>
                        {/if}
                    </td>
                  </tr>
                  <tr>
                    <th>{"رقم الجوال"|gettext}</th>
                    <td>{$contract.buyer.profile[0].mobile}</td>
                  </tr>
                    {if $type eq "rent"}
                      <tr>
                        <th>{"قيمة الإيجار"|gettext}</th>
                        <td>{$contract.rentvalue} {$config.currency}</td>
                      </tr>
                    {else}
                      <tr>
                        <th>{"طريقة الدفع"|gettext}</th>
                        <td>{$contract.paytypename}</td>
                      </tr>
                    {/if}
                </table>
              </div>
                {if $type eq "rent"}
                  <div class="col-md-3">
                    <table>
                      <tr>
                        <th>{"مدة العقد"|gettext}</th>
                        <td>{$contract.periodnum} {$contract.period_title}</td>
                      </tr>
                      <tr>
                        <th>{"ينتهي في"|gettext}</th>
                        <td>{$contract.enddate|ardate:false:false}</td>
                      </tr>
                      <tr>
                        <th>{"دورة الدفع"|gettext}</th>
                        <td>{$contract.payment_title}</td>
                      </tr>
                    </table>
                  </div>
                {/if}
            </div>
          </div>
            {if $type eq "rent" AND $person eq "owner"}
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
                        {foreach $contract.contracts_timeline as $contract_date}
                          <li data-timeline-node="{ start:'{$contract_date.start} 00:00', end:'{$contract_date.end} 00:00', bgColor:'#8bbf61', color:'#fff', label:'{$contract_date.start} - {$contract_date.end}' }"></li>
                        {/foreach}
                    </ul>
                  </div>
                </div>
              </div>
            {/if}
        </td>
      </tr>
    </table>
  </div>
</div>

<div class="widget widget-body-white">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-money"></i> {"تفاصيل الدفعات"|gettext}</h4>
  </div>
  <div class="widget-body">
    <table class="table table-condensed table-striped table-primary table-vertical-center">
      <thead>
        <tr>
          <th class="center">{"الرقم"|gettext}</th>
          <th class="center" style="width: 150px">{"القيمة"|gettext}</th>
          <th class="center">{"التفاصيل"|gettext}</th>
          <th class="center">{"تاريخ الإستحقاق"|gettext}</th>
          <th class="center">{"تاريخ التحصيل"|gettext}</th>
          <th class="center">{"الحالة"|gettext}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$results item=result}
          <tr>
            <td class="center">{$result.id}</td>
            <td class="center">
              <span class="label label-primary marginTB display-block">{$result.amount} {$config.currency}</span>
                {if !empty($result.paidamount)}
                  <span class="label label-danger marginTB display-block">-{$result.paidamount} {$config.currency}</span>
                {/if}
            </td>
            <td class="center"><span class="label label-stroke label-info">{$result.pay_details}</span></td>
            <td class="center"><span class="label label-primary">{$result.paydate|ardate:false:false}</span></td>
            <td class="center"><span class="label label-warning">{if $result.gone eq 1}{$result.gone_date|ardate:false:false}{/if}</span></td>
            <td class="center">
              {if $result.gone eq 1}
                <span class="label label-success">{"تم التحصيل"|gettext}</span>
              {elseif strtotime($result.paydate) lt $smarty.const.TIMENOW}
                <span class="label label-danger">{"يجب السداد"|gettext}</span>
              {else}
                <span class="label label-default">{"في الإنتظار"|gettext}</span>
              {/if}
            </td>
          </tr>
        {foreachelse}
          <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
        {/foreach}
      </tbody>
    </table>
    <div class="clearfix"></div>
  </div>
</div>
