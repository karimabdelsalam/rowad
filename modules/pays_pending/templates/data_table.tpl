<table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
  <thead>
  <tr>
    {if empty($shortcut)}
    <th style="width: 1%;" class="uniformjs">
      <input type="checkbox" class="checkAll" />
    </th>
    {/if}
    <th class="center" style="width:80px">{"م"|gettext}</th>
    <th class="center">{"المبلغ"|gettext}</th>
    <th class="center">{"تاريخ الإستحقاق"|gettext}</th>
    <th class="center">{"قيمة"|gettext}</th>
    <th class="center">{"المالك"|gettext}</th>
    <th class="center">{"العقار"|gettext}</th>
    <th class="center">{"العميل"|gettext}</th>
    <th class="center">{"الربح"|gettext}</th>
    {if empty($shortcut) AND $cuaction.name neq "list"}
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
        <span class="label label-primary marginTB display-block">{$result.amount}</span>
          {if !empty($result.paidamount)}
            <span class="label label-danger marginTB display-block">-{$result.paidamount}</span>
          {/if}
      </td>
      <td class="center"><span class="label label-default">{$result.paydate|ardate:false:false}</span></td>
      <td class="center">
        <span class="label label-warning">{$result.paymenttype|gettext}</span> <a href="{$CPURL}/{$result.module}/edit?id={$result.contractid}" target="_blank"><span class="label label-warning"><i class="fa fa-link"></i></span></a>
          {if $result.invoiceid gt 0}
            <a href="{$CPURL}/invoices/printable?id={$result.invoiceid}" data-toggle="tooltip" title="{"فاتورة"|gettext}" target="_blank"><span class="label label-default"><i class="fa fa-newspaper-o"></i></span></a>
            <a href="{$CPURL}/invoices/taxbill?id={$result.invoiceid}" data-toggle="tooltip" title="{"فاتورة ضريبية"|gettext}" target="_blank"><span class="label label-default"><i class="fa fa-file-text-o"></i></span></a>
          {/if}
          {if $result.statoutid gt 0}
            <a href="{$CPURL}/statementsout/edit?id={$result.statoutid}" data-toggle="tooltip" title="{"سند صرف"|gettext}" class="quick-form"><span class="label label-default"><i class="fa fa-file-o"></i></span></a>
          {/if}
      </td>
      <td class="">
          {section name=op loop=count($result.owner.id)}
            <span class="label label-info marginTB" data-toggle="tooltip" data-placement="right" title="{$result.owner.name[op]}">
                {$result.ownerinfo[op].fname} {$result.ownerinfo[op].fathname}
            </span><br />
          {/section}
      </td>
      <td class="center"><span class="label label-danger">{$result.build}</span></td>
      <td class="">
          {section name=op loop=count($result.relatedto.name)}
            <span class="label label-info marginTB">
                {$result.relatedto.name[op]}
            </span><br />
          {/section}
      </td>
      <td class="center">
          {if !empty($result.commission)}
            <span class="label label-success" data-toggle="tooltip" data-placement="top" title="{"عمولة المكتب يتحمله المالك"|gettext}">{$result.commission} {$config.currency}</span>
          {/if}
          {if !empty($result.commission2)}
            <span class="label label-warning" data-toggle="tooltip" data-placement="top" title="{"عمولة المكتب يتحمله العميل"|gettext}">{$result.commission2} {$config.currency}</span>
          {/if}
      </td>
      {if empty($shortcut) AND $cuaction.name neq "list"}
      <td class="text-left center no-print">
          {include file="core/templates/module_actions.tpl" module=$cumodule.name}
      </td>
      {/if}
    </tr>
      {foreachelse}
    <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
  {/foreach}
  </tbody>
</table>
