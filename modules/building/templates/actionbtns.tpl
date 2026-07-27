{if $result.type eq "rent"}
  {if $result.active eq 1}
  <li><a href="{$CPURL}/rent/add/?id={$result.id}"><i class="fa fa-plus-circle"></i>&nbsp;&nbsp;{"عقد جديد"|gettext}</a></li>
  {/if}
  <li><a href="{$CPURL}/rent/index/?id={$result.id}"><i class="fa fa-eye"></i>&nbsp;&nbsp;{"عرض العقود"|gettext}</a></li>
{else}
  {if $result.available eq 1 AND $result.active eq 1}
  <li><a href="{$CPURL}/sell/add/?id={$result.id}"><i class="fa fa-plus-circle"></i>&nbsp;&nbsp;{"عقد جديد"|gettext}</a></li>
  {/if}
  <li><a href="{$CPURL}/sell/index/?id={$result.id}"><i class="fa fa-eye"></i>&nbsp;&nbsp;{"عرض العقود"|gettext}</a></li>
{/if}
<li><a href="{$CPURL}/statementsout/add/?buildid={$result.id}" class="quick-form"><i class="fa fa-exchange"></i>&nbsp;&nbsp;{"إضافة مصروفات"|gettext}</a></li>
<li><a href="{$CPURL}/building/reports/index/?buildid={$result.id}"><i class="fa fa-area-chart"></i>&nbsp;&nbsp;{"عرض التقارير"|gettext}</a></li>
