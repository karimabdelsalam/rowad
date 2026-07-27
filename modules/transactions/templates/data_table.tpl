<table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
  <thead>
  <tr>
      {if empty($shortcut)}
        <th style="width: 1%;" class="center uniformjs">
          <input type="checkbox" class="checkAll" />
        </th>
      {/if}
    <th class="center" style="width:80px">{"م"|gettext}</th>
    <th class="center">{"المبلغ"|gettext}</th>
    <th class="center">{"النوع"|gettext}</th>
    <th class="center">{"التاريخ"|gettext}</th>
      {if $cuaction.name eq "processed"}
        <th class="center">{"تاريخ الترحيل"|gettext}</th>
      {/if}
    <th class="center">{"المالك"|gettext}</th>
    <th class="center">{"العقار"|gettext}</th>
    {if empty($shortcut) AND $cuaction.name neq "index"}
    <th class="center no-print" style="width: 150px;"></th>
    {/if}
  </tr>
  </thead>
  <tbody>
  {foreach from=$results item=result}
    <tr class="selectable">
        {if empty($shortcut)}
          <td class="center uniformjs">
            <input type="checkbox" name="ids[]" value="{$result.id}" />
          </td>
        {/if}
      <td class="center">{$result.id}</td>
      <td class="center">
          {if $result.direction eq "debit"}
            <span class="label label-success">+{$result.amount}</span>
          {else}
            <span class="label label-danger">-{$result.amount}</span>
          {/if}
          {if $result.tax gt 0}
            <br><span class="label label-danger marginTB" title="{"ضريبة الدخل"|gettext}" data-toggle="tooltip">-{$result.tax}</span>
          {/if}
      </td>
      <td class="center">
          {if $result.paymentid gt 0 AND $result.ownerid gt 0 AND $result.direction eq "debit"}
            <span class="label label-warning">{$result.paytype|gettext|clean}</span>
          {else}
            <span class="label label-warning">{$result.transtype|gettext|clean}</span>
          {/if}
          {if $result.statinid gt 0}
            <a href="{$CPURL}/statementsin/edit?id={$result.statinid}" data-toggle="tooltip" title="{"سند قبض"|gettext}" class="quick-form"><span class="label label-default"><i class="fa fa-file-text-o"></i></span></a>
          {/if}
          {if $result.statoutid gt 0}
            <a href="{$CPURL}/statementsout/edit?id={$result.statoutid}" data-toggle="tooltip" title="{"سند صرف"|gettext}" class="quick-form"><span class="label label-default"><i class="fa fa-file-o"></i></span></a>
          {/if}
      </td>
      <td class="center"><span class="label label-default">{$result.paydate|ardate:false:false}</span></td>
        {if $cuaction.name eq "processed"}
          <td class="center"><span class="label label-default">{$result.gone_date|ardate:false:false}</span></td>
        {/if}
      <td class="center">
          {if empty($result.fname)}
            <span class="label label-info">{"حساب المؤسسة"|gettext}</span>
          {else}
            <span class="label label-info">{$result.fname} {$result.fathname} {$result.lname} {$result.famname}</span>
          {/if}
      </td>
      <td class="center"><span class="label label-default">{$result.build}</span></td>
        {if empty($shortcut) AND $cuaction.name neq "index"}
          <td class="text-left center no-print">
              {include file="core/templates/module_actions.tpl" module=$cumodule.name}
          </td>
        {/if}
    </tr>
      {foreachelse}
    <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
  {/foreach}
  {if !empty($results) AND empty($shortcut)}
    <tr>
      <td class="center">{"اجمالي"|gettext}</td>
      <td colspan="20">
        <span class="label label-success">{$results[0].total_debit|round:2}</span>
        <span class="label label-default">-</span>
        <span class="label label-danger">{$results[0].total_credit|round:2}</span>
        <span class="label label-default">=</span>
        <span class="label label-info">{$results[0].total_debit|round:2-$results[0].total_credit|round:2} {$config.currency}</span>
      </td>
    </tr>
  {/if}
  </tbody>
</table>
