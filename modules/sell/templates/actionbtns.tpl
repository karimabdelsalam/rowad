{if $result.archive eq 0}
  <li><a href="{$CPURL}/{$module}/archiveit/?id={$result.id}" class="do-ajax"><i class="fa fa-archive"></i>&nbsp;&nbsp;{"ارسال للأرشيف"|gettext}</a></li>
{else}
  <li><a href="{$CPURL}/{$module}/unarchive/?id={$result.id}" class="do-ajax"><i class="fa fa-inbox"></i>&nbsp;&nbsp;{"ارسال للعقود"|gettext}</a></li>
{/if}
<li><a href="{$CPURL}/statementsout/add/?buildid={$result.buildid}" class="quick-form"><i class="fa fa-exchange"></i>&nbsp;&nbsp;{"إضافة مصروفات"|gettext}</a></li>