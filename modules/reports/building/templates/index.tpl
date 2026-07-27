<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"اسم العقار"|gettext}</label>
      <div class="col-md-3">
        <select class="buildingAjaxSearch noSelect2" name="buildid" style="width:100%" required>
          {if $smarty.get.buildid}<option value="{$smarty.get.buildid}">{$build.title} {if !empty($build.location)} [{$build.location}]{/if}</option>{/if}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"الحالة"|gettext}</label>
      <div class="col-md-2">
        <select name="status" style="width:100%">
          <option value="">{"غير محدد"|gettext}</option>
          <option value="processed" {if $smarty.get.status eq "processed"}selected="selected"{/if}>{"مرحل"|gettext}</option>
          <option value="pending" {if $smarty.get.status eq "pending"}selected="selected"{/if}>{"غير مرحل"|gettext}</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"فترة زمنية"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder="{"من"|gettext}" />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"إلي"|gettext}" />
      </div>
    </div>
    <div class="row col-md-12">
      <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث وتنقيح"|gettext}</button>
    </div>
    <div class="clearfix"></div>
  </form>
</div>

<!-- Chart Graph -->
<div class="row chartGraphs">
  <div class="col-md-8">
    <!-- Icon only Tabs -->
    <div class="relativeWrap">
      <div class="widget widget-4 widget-tabs-icons-only margin-bottom-none">
        <div class="widget-head" style="border-top-left-radius:0">
          <h4 class="heading"><i class="fa fa-line-chart"></i> {"تقرير مالي"|gettext}</h4>
          <ul class="pull-right">
            <li>{"استعراض"|gettext}</li>
            <li class="withicon active">
              <span data-toggle="tab" data-target="#stat-income"><i class="fa fa-money"></i> {"إيرادات"|gettext}</span>
            </li>
            <li class="withicon">
              <span data-toggle="tab" data-target="#stat-delays"><i class="fa fa-gavel"></i>{"متأخرات"|gettext}</span>
            </li>
            <li class="withicon">
              <span data-toggle="tab" data-target="#stat-credit"><i class="fa fa-credit-card"></i>{"مديونيات"|gettext}</span>
            </li>
          </ul>
          <div class="clearfix"></div>
        </div>
        <div class="widget-body">
          <div class="tab-content stat-tab-content">
            <div id="stat-income" class="tab-pane active box-generic">
              <canvas id="horizontalBar1" width="600" height="600"></canvas>
            </div>
            <div id="stat-delays" class="tab-pane box-generic">
              <canvas id="horizontalBar2" width="600" height="600"></canvas>
            </div>
            <div id="stat-credit" class="tab-pane box-generic">
              <canvas id="horizontalBar3" width="600" height="600"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- // Icon only Tabs END -->
  </div>
  <div class="col-md-4">
    <div class="widget widget-body-white">
      <canvas id="pieChart" width="300" height="300"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget widget-body-white">
      <canvas id="pieChart2" width="300" height="300"></canvas>
    </div>
  </div>
</div>
<!-- Chart Graph -->

{if !empty($results[0].report.contracts_timeline)}
<div class="row">
  <div class="col-md-12">
    <div class="widget widget-body-white">
      <div class="widget-head">
        <h4 class="heading"><i class="fa fa-clock-o"></i> {"الخط الزمني للعقود"|gettext}</h4>
      </div>
      <div style="max-width: 70vw;padding-top: 10px;width: 900px;text-align: center;margin: auto">
        <div class="btn-group btn-group-xs pull-left" style="margin-bottom: 5px;z-index: 2;">
          <button type="button" class="btn btn-default timelineScaleTo" data-scale="day">{"يوم"|gettext}</button>
          <button type="button" class="btn btn-default timelineScaleTo" data-scale="month">{"شهر"|gettext}</button>
          <button type="button" class="btn btn-default timelineScaleTo" data-scale="year">{"عام"|gettext}</button>
          <button type="button" class="btn btn-default timelineScaleTo" data-scale="zoomout"><i class="fa fa-search-minus"></i></button>
          <button type="button" class="btn btn-default timelineScaleTo" data-scale="zoomin"><i class="fa fa-search-plus"></i></button>
        </div>
        <div class="horzTimeline">
          <ul class="timeline-events">
              {foreach $results[0].report.contracts_timeline as $contract_date}
                <li data-timeline-node="{ start:'{$contract_date.start} 00:00', end:'{$contract_date.end} 00:00', bgColor:'#8bbf61', color:'#fff', label:'{$contract_date.start} - {$contract_date.end}' }"></li>
              {/foreach}
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
{/if}

<div class="widget widget-body-white">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <form action="{$CPURL}/{$cumodule.name}/" method="get">
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
        <thead>
          <tr>
            <th class="center" style="width:80px">{"م"|gettext}</th>
            <th class="center">{"المبلغ"|gettext}</th>
            <th class="center">{"رقم الدفعة"|gettext}</th>
            <th class="center">{"تاريخ الإستحقاق"|gettext}</th>
            <th class="center">{"تاريخ التحصيل"|gettext}</th>
            <th class="center">{"السبب"|gettext}</th>
            <th class="center">{"الحالة"|gettext}</th>
            <th class="center no-print" style="width: 150px;"></th>
          </tr>
        </thead>
        <tbody>
          {foreach name=op from=$results item=result}
            <tr class="selectable">
              <td class="center">{$smarty.foreach.op.iteration}</td>
              <td class="center"><span class="label label-danger">{$result.amount}</span></td>
              <td class="center"><span class="label label-primary">{$result.id}</span></td>
              <td class="center"><span class="label label-success">{$result.paydate|ardate:false:false}</span></td>
              <td class="center">{if $result.gone eq 1}<span class="label label-default">{$result.gone_date|ardate:false:false}</span>{/if}</td>
              <td class="center"><span class="label label-danger">{$result.paymenttype}</span></td>
              <td class="center">{if $result.gone eq 1}<span class="label label-default">{"مرحل"|gettext}{else}<span class="label label-warning">{"غير مرحل"|gettext}{/if}</span></td>
              <td class="text-left center no-print">
                {include file="core/templates/module_actions.tpl"}
              </td>
            </tr>
          {foreachelse}
            <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
          {/foreach}
          {if !empty($results)}
            <tr>
              <td class="center"><span class="label label-info">{"إجمالي"|gettext}</span></td>
              <td colspan="20">
                <span class="label label-default">{$results[0].report.totalAmounts|number_format:2} {$config.currency}</span>
              </td>
            </tr>
          {/if}
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
