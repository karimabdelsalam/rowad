<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"فترة زمنية"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder="{"من"|gettext}" />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"إلي"|gettext}" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"نوع المستحقات"|gettext}</label>
      <div class="col-md-3">
        <select name="type">
            <option value="all">{"الجميع"|gettext}</option>
            <option value="rent" {if $smarty.get.type eq "rent"}selected="selected"{/if}>{"الإيجارات فقط"|gettext}</option>
            <option value="expenses" {if $smarty.get.type eq "expenses"}selected="selected"{/if}>{"المصاريف فقط"|gettext}</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"نوع البحث"|gettext}</label>
      <div class="col-md-3">
        <select name="transtype">
            <option value="all">{"الجميع"|gettext}</option>
            <option value="unpaid" {if $smarty.get.transtype eq "unpaid"}selected="selected"{/if}>{"المتأخرات فقط"|gettext}</option>
            <option value="paid" {if $smarty.get.transtype eq "paid"}selected="selected"{/if}>{"المدفوع فقط"|gettext}</option>
        </select>
      </div>
    </div>
    <div class="row col-md-12">
      <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"عرض التقرير"|gettext}</button>
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
            <th class="center">{"اسم المستأجر"|gettext}</th>
            <th class="center">{"رقم الهاتف"|gettext}</th>
            <th class="center">{"العقار"|gettext}</th>
            <th class="center">{"موقع العقار"|gettext}</th>
            <th class="center">{"تفاصيل العقار"|gettext}</th>
            <th class="center">{"المبلغ"|gettext}</th>
            <th class="center">{"السبب"|gettext}</th>
            <th class="center">{"المدفوع"|gettext}</th>
            <th class="center">{"المستحق"|gettext}</th>
            <th class="center">{"التاريخ"|gettext}</th>
            <th class="center">{"ملاحظات"|gettext}</th>
            <th class="center">{"الحالة"|gettext}</th>
          </tr>
        </thead>
        <tbody>
          {foreach name=op from=$results item=result}
            <tr class="selectable">
              <td class="center">{$smarty.foreach.op.iteration}</td>
              <td class="center"><span class="label label-danger">{$result.buyerName}</span></td>
              <td class="center"><span class="label label-primary">{$result.buyerMobile}</span></td>
              <td class="center"><span class="label label-primary">{$result.location} {$result.build}</span></td>
              <td class="center"><span class="label label-primary">{$result.loc_details}</span></td>
              <td class="center"><span class="label label-primary">{$result.details}</span></td>
              <td class="center"><span class="label label-default">{$result.amount}</span></td>
              <td class="center"><span class="label label-primary">{$result.paymenttype}</span></td>
              <td class="center"><span class="label label-default">{$result.paidamount}</span></td>
              <td class="center"><span class="label label-default">{$result.amount-$result.paidamount}</span></td>
              <td class="center"><span class="label label-success">{$result.paydate|ardate:false:false}</span></td>
              <td class="center"><span class="label label-success">{$result.contract_notes}</span></td>
              {if $result.gone eq 1}
                <td class="center">{"مدفوعة"|gettext}</td>
              {else}
                <td class="center" style="background-color: #ff4646; color: #fff">{"غير مدفوعة"|gettext}</td>
              {/if}
            </tr>
          {foreachelse}
            <tr class="warning"><td class="center" colspan="20">{"لا يوجد اي بيانات لنتيجة بحثك"|gettext}</td></tr>
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
