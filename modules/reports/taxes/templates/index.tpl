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
            <th class="center">{"رقم الحركة"|gettext}</th>
            <th class="center">{"التاريخ"|gettext}</th>
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
              <td class="center">{if $result.gone eq 1}<span class="label label-default">{"مرحل"|gettext}{else}<span class="label label-warning">{"غير مرحل"|gettext}{/if}</span></td>
              <td class="text-left center no-print">
                {include file="core/templates/module_actions.tpl"}
              </td>
            </tr>
          {foreachelse}
            <tr class="warning"><td class="center" colspan="20">{"لا يوجد اي بيانات متعلقة بالضرائب في تلك الفترة"|gettext}</td></tr>
          {/foreach}
          {if !empty($results)}
            <tr>
              <td class="center"><span class="label label-info">{"إجمالي"|gettext}</span></td>
              <td colspan="20">
                <span class="label label-default">{$results[0].totalAmounts|number_format:2} {$config.currency}</span>
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
