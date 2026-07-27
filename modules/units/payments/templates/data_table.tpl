<table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
  <thead>
  <tr>
    <th class="center" style="width:80px">{"م"|gettext}</th>
    <th class="center">{"المبلغ"|gettext}</th>
    <th class="center"></th>
    <th class="center">{"تاريخ الإستحقاق"|gettext}</th>
    <th class="center">{"قيمة"|gettext}</th>
    <th class="center">{"المالك"|gettext}</th>
    <th class="center">{"العقار"|gettext}</th>
    <th class="center">{"العميل"|gettext}</th>
    <th class="center">{"عمولة المكتب"|gettext}</th>
    {if empty($shortcut)}
    <th class="center no-print" style="width: 150px;"></th>
    {/if}
  </tr>
  </thead>
  <tbody>
  {foreach from=$results item=result}
    <tr class="selectable">
      <td class="center">{$result.id}</td>
      <td class="center">
        <span class="label label-primary marginTB display-block">{$result.amount}</span>
          {if !empty($result.paidamount)}
            <span class="label label-danger marginTB display-block">-{$result.paidamount}</span>
          {/if}
      </td>
      <td class="center">
          {if $result.gone eq 1}
            <span class="badge badge-success" style="border-radius: 15px;padding: 5px;" data-toggle="tooltip" title="{"محصلة"|gettext}" data-placement="top"><i class="fa fa-check"></i></span>
          {elseif $result.gone eq 0 AND strtotime($result.paydate) > time()}
            <span class="badge badge-primary" style="border-radius: 15px;padding:6px 7px" data-toggle="tooltip" title="{"مجدولة"|gettext}" data-placement="top"><i class="fa fa-clock-o"></i></span>
          {else}
            <span class="badge badge-danger" style="border-radius: 15px;padding:6px 7px" data-toggle="tooltip" title="{"متأخرات"|gettext}" data-placement="top"><i class="fa fa-times"></i></span>
          {/if}
      </td>
      <td class="center"><span class="label label-default">{$result.paydate|ardate:false:false}</span></td>
      <td class="center">
        <span class="label label-warning">{$result.paymenttype|gettext}</span>
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
            <span class="label label-default" data-toggle="tooltip" data-placement="top" title="{"عمولة المكتب يتحمله المالك"|gettext}">{$result.commission} {$config.currency}</span>
          {/if}
          {if !empty($result.commission2)}
            <span class="label label-warning" data-toggle="tooltip" data-placement="top" title="{"عمولة المكتب يتحمله العميل"|gettext}">{$result.commission2} {$config.currency}</span>
          {/if}
      </td>
      {if empty($shortcut)}
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
