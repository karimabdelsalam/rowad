<table class="table table-condensed table-striped table-primary table-vertical-center">
  <thead>
  <tr>
    <th class="center" style="width:80px">{"م"|gettext}</th>
    <th class="center">{"المبلغ"|gettext}</th>
    <th class="center">{"النوع"|gettext}</th>
    <th class="center">{"التاريخ"|gettext}</th>
    <th class="center">{"العقار"|gettext}</th>
    <th class="center">{"تاريخ الاستلام"|gettext}</th>
  </tr>
  </thead>
  <tbody>
  {foreach from=$results item=result}
    <tr class="selectable">
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
            <a href="{$CPURL}/{$cumodule.name}/printstatin/?id={$result.statinid}" data-toggle="tooltip" title="{"سند قبض"|gettext}" target="_blank"><span class="label label-default"><i class="fa fa-file-text-o"></i></span></a>
          {/if}
          {if $result.statoutid gt 0}
            <a href="{$CPURL}/{$cumodule.name}/printstatout/?id={$result.statoutid}" data-toggle="tooltip" title="{"سند صرف"|gettext}" target="_blank"><span class="label label-default"><i class="fa fa-file-o"></i></span></a>
          {/if}
      </td>
      <td class="center"><span class="label label-primary">{$result.paydate|ardate:false:false}</span></td>
      <td class="center"><span class="label label-default">{$result.build}</span></td>
      <td class="center">{if $result.gone eq 1}<span class="label label-success">{$result.gone_date|ardate:false:false}</span>{else}-{/if}</td>
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
