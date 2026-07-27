<!-- Widget -->
<div class="widget overflow-hidden">
    <h4 class="innerAll bg-gray border-bottom margin-bottom-none"><i class="fa fa-money"></i> {"مدفوعات مستحقة الدفع"|gettext}</h4>
    <div class="widget-body">
        <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
          <thead>
            <tr>
              <th class="center" style="width:80px">{"م"|gettext}</th>
              <th class="center">{"المبلغ"|gettext}</th>
              <th class="center">{"تاريخ استلامه"|gettext}</th>
              <th class="center">{"قيمة"|gettext}</th>
              <th class="center">{"العقار"|gettext}</th>
              {if $smarty.session.userinfo.groupid eq 1}
              <th class="center">{"الربح"|gettext}</th>
              {/if}
            </tr>
          </thead>
          <tbody>
            {foreach from=$endedPayments item=result}
              <tr>
                <td class="center">{$result.id}</td>
                <td class="center"><span class="label label-primary">{$result.amount}</span></td>
                <td class="center"><span class="label label-default">{$result.paydate|ardate:false:false}</span></td>
                {if $smarty.session.userinfo.groupid eq 1}
                  <td class="center">
                    <span class="label label-warning">{$result.paymenttype|gettext}</span> <a href="{$CPURL}/{$result.module}/edit?id={$result.contractid}" title="{"تفاصيل العقد"|gettext}" data-toggle="tooltip" data-placement="bottom" class="quick-form"><span class="label label-warning"><i class="fa fa-link"></i></span></a>
                    {if $result.module eq "rent"}
                    <a href="{$CPURL}/pays_pending/index/?rentid={$result.contractid}" title="{"عرض جميع المدفوعات"|gettext}" data-toggle="tooltip" data-placement="bottom" class="quick-form"><span class="label label-warning"><i class="fa fa-search"></i></span></a>
                    {else}
                    <a href="{$CPURL}/pays_pending/index/?sellid={$result.contractid}" title="{"عرض جميع المدفوعات"|gettext}" data-toggle="tooltip" data-placement="bottom" class="quick-form"><span class="label label-warning"><i class="fa fa-search"></i></span></a>
                    {/if}
                  </td>
                {elseif $smarty.session.userinfo.groupid eq 2}
                  <td class="center">
                    <span class="label label-warning">{$result.paymenttype|gettext}</span></a>
                      {if $result.module eq "rent"}
                        <a href="{$CPURL}/units/payments/index/?rentid={$result.contractid}&type=late" title="{"عرض جميع المدفوعات"|gettext}" data-toggle="tooltip" data-placement="bottom" class="quick-form"><span class="label label-warning"><i class="fa fa-search"></i></span></a>
                      {else}
                        <a href="{$CPURL}/units/payments/index/?sellid={$result.contractid}&type=late" title="{"عرض جميع المدفوعات"|gettext}" data-toggle="tooltip" data-placement="bottom" class="quick-form"><span class="label label-warning"><i class="fa fa-search"></i></span></a>
                      {/if}
                  </td>
                {elseif $smarty.session.userinfo.groupid eq 3}
                  <td class="center">
                    <span class="label label-warning">{$result.paymenttype|gettext}</span></a>
                      {if $result.module eq "rent"}
                        <a href="{$CPURL}/ccp/payments/index/?rentid={$result.contractid}&type=late" title="{"عرض جميع المدفوعات"|gettext}" data-toggle="tooltip" data-placement="bottom" class="quick-form"><span class="label label-warning"><i class="fa fa-search"></i></span></a>
                      {else}
                        <a href="{$CPURL}/ccp/payments/index/?sellid={$result.contractid}&type=late" title="{"عرض جميع المدفوعات"|gettext}" data-toggle="tooltip" data-placement="bottom" class="quick-form"><span class="label label-warning"><i class="fa fa-search"></i></span></a>
                      {/if}
                  </td>
                {/if}
                <td class="center"><span class="label label-danger">{$result.build}</span></td>
                {if $smarty.session.userinfo.groupid eq 1}
                <td class="center"><span class="label label-success">{if $result.comm_type eq "money"}{$result.commission} {$config.currency}{else}{$result.commission}%{/if}</span></td>
                {/if}
              </tr>
            {foreachelse}
              <tr class="warning"><td class="center" colspan="20">{"لا يوجد اي دفعات مستحقة الدفع حالياً"|gettext}</td></tr>
            {/foreach}
          </tbody>
        </table>
    </div>
</div>
<!-- //Widget -->
