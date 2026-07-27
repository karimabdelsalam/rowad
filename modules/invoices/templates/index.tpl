<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2 control-label">{"رقم الفاتورة"|gettext}</label>
      <div class="col-md-2">
        <input type="text" class="form-control" name="serial" value="{$smarty.get.serial}" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2 control-label">{"اسم المستفيد"|gettext}</label>
      <div class="col-md-4">
        <input type="text" class="form-control" name="fullname" value="{$smarty.get.fullname}" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"تاريخ الإصدار"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder="{"من"|gettext}" />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"الى"|gettext}" />
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
            <th style="width: 1%;" class="uniformjs">
              <input type="checkbox" class="checkAll" />
            </th>
            <th class="center" style="width:80px">{"رقم الفاتورة"|gettext}</th>
            <th class="center">{"المستفيد"|gettext}</th>
            <th class="center">{"تاريخ المعاملة"|gettext}</th>
            <th class="center">{"الإجمالي"|gettext}</th>
            <th class="center">{"المدفوع"|gettext}</th>
            <th class="center">{"الباقي"|gettext}</th>
            <th class="center">{"تاريخ الإصدار"|gettext}</th>
            <th class="center no-print" style="width: 150px;"></th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$results item=result}
            <tr class="selectable">
              <td class="center uniformjs">
                <input type="checkbox" name="ids[]" value="{$result.id}" />
              </td>
              <td class="center">{$result.id}</td>
              <td>
                <strong>{$result.customer}</strong>
              </td>
              <td class="center"><span class="label label-primary">{$result.created_date|ardate:false:false}</span></td>
              <td class="center"><span class="label label-success">{$result.total}</span></td>
              <td class="center"><span class="label label-info">{$result.paid}</span></td>
              <td class="center"><span class="label label-warning">{$result.total-$result.paid+$result.paidamounts}</span></td>
              <td class="center"><span class="label label-primary">{$result.inserted_time|ardate:false:false}</span></td>
              <td class="text-left center no-print">
                {include file="core/templates/module_actions.tpl"}
              </td>
            </tr>
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
