<!-- Widget -->
<div class="widget overflow-hidden">
    <h4 class="innerAll bg-gray border-bottom margin-bottom-none"><i class="fa fa-paperclip"></i> {"اخر حركات الحساب الرئيسي"|gettext}</h4>
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center">
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
        {foreach from=$accTransactions item=result}
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
            <td class="center"><span class="label label-primary">{$result.createdtime|ardate:true:false}</span></td>
          </tr>
          {foreachelse}
          <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
        {/foreach}
        </tbody>
      </table>
    </div>
</div>
<!-- //Widget -->
