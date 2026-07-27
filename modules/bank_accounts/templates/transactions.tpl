<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"تاريخ العملية"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder={"من"|gettext} />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder={"إلي"|gettext} />
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
            <th class="center">{"النوع"|gettext}</th>
            <th class="center">{"رقم السند"|gettext}</th>
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
                <a href="{$CPURL}/statementsin/edit/?id={$result.statementid}" target="_blank">#{$result.statementid}</a>
                {else}
                  <a href="{$CPURL}/statementsout/edit/?id={$result.statementid}" target="_blank">#{$result.statementid}</a>
                {/if}
              </td>
              <td class="center"><span class="label label-primary">{$result.createdtime|ardate:true:false}</span></td>
            </tr>
          {foreachelse}
            <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
            {/foreach}
        </tbody>
      </table>
      {include file="core/templates/paging.tpl"}
      <div class="clearfix"></div>
    </div>
  </form>
</div>
