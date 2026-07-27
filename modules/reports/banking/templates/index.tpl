<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"الحساب"|gettext}</label>
      <div class="col-md-3">
        <select name="account" class="form-control">
          <option value="">{"اختر حساب"|gettext}</option>
          {foreach $bankaccounts as $bankaccount}
          <option value="{$bankaccount.id}" {if $smarty.get.account eq $bankaccount.id}selected="selected"{/if}>{$bankaccount.fullname} - {$bankaccount.bank} ({$bankaccount.accno})</option>
          {/foreach}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"التاريخ"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder="{"من"|gettext}" />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"إلي"|gettext}" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"المبلغ"|gettext}</label>
      <div class="col-md-2">
        <input type="text" name="famount" value="{$smarty.get.famount}" class="form-control" placeholder="{"من"|gettext}" />
      </div>
      <div class="col-md-2">
        <input type="text" name="toamount" value="{$smarty.get.toamount}" class="form-control" placeholder="{"إلي"|gettext}" />
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
    <div class="widget widget-body-white">
      <canvas id="horizontalBar" width="600" height="600"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget widget-body-white">
      <canvas id="pieChart" width="300" height="300"></canvas>
    </div>
  </div>
</div>
<!-- Chart Graph -->

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
          <th class="center">{"النوع"|gettext}</th>
          <th class="center">{"رقم السند"|gettext}</th>
          <th class="center">{"السبب"|gettext}</th>
          <th class="center">{"التوقيت"|gettext}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$results item=result}
          <tr class="selectable">
            <td class="center">{$result.id}</td>
            <td class="center">
                {if $result.type eq "credit"}
                  <span class="label label-success">+{$result.amount} {$config.currency}</span>
                {else}
                  <span class="label label-default">-{$result.amount} {$config.currency}</span>
                {/if}
            </td>
            <td class="center">
                {if $result.type eq "credit"}
                  <span class="label label-success">{"دائن"|gettext}</span>
                {else}
                  <span class="label label-default">{"مدين"|gettext}</span>
                {/if}
            </td>
            <td class="center">
                {if $result.type eq "credit"}
                  <a href="{$CPURL}/statementsin/edit/?id={$result.statementid}" class="quick-form">#{$result.statementid}</a>
                {else}
                  <a href="{$CPURL}/statementsout/edit/?id={$result.statementid}" class="quick-form">#{$result.statementid}</a>
                {/if}
            </td>
            <td class="center">
                {if $result.type eq "credit"}
                  <span class="label label-warning">{$result.in_reason}</span>
                {else}
                  <span class="label label-warning">{$result.out_reason}</span>
                {/if}
            </td>
            <td class="center"><span class="label label-primary">{$result.createdtime|ardate:true:false}</span></td>
          </tr>
        {foreachelse}
          <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
        {/foreach}
        {if !empty($results)}
          <tr>
            <td class="center">{"اجمالي"|gettext}</td>
            <td colspan="20">
              <span class="label label-success">{$results[0].report.credit.totalValue|round:2}</span>
              <span class="label label-default">-</span>
              <span class="label label-danger">{$results[0].report.debit.totalValue|round:2}</span>
              <span class="label label-default">=</span>
              <span class="label label-info">{$results[0].report.credit.totalValue|round:2-$results[0].report.debit.totalValue|round:2} {$config.currency}</span>
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
