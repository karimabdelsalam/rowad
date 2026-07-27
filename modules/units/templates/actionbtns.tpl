{if $result.type eq "rent"}
  <li><a href="{$CPURL}/units/rent/index/?id={$result.id}"><i class="fa fa-eye"></i>&nbsp;&nbsp;{"عرض العقود"|gettext}</a></li>
{else}
  <li><a href="{$CPURL}/units/sell/index/?id={$result.id}"><i class="fa fa-eye"></i>&nbsp;&nbsp;{"عرض العقود"|gettext}</a></li>
{/if}
<li><a href="{$CPURL}/units/reports/index/?buildid={$result.id}"><i class="fa fa-area-chart"></i>&nbsp;&nbsp;{"عرض التقارير"|gettext}</a></li>
