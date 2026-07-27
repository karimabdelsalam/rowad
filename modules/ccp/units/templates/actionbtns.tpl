{if $result.type eq "rent"}
  <li><a href="{$CPURL}/ccp/rent/index/?id={$result.id}"><i class="fa fa-eye"></i>&nbsp;&nbsp;{"عرض العقود"|gettext}</a></li>
{else}
  <li><a href="{$CPURL}/ccp/sell/index/?id={$result.id}"><i class="fa fa-eye"></i>&nbsp;&nbsp;{"عرض العقود"|gettext}</a></li>
{/if}
