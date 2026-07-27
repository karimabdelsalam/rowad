<div class="btn-group no-print">
  <button class="btn btn-default" data-toggle="dropdown" type="button"><i class="fa fa-cogs"></i>&nbsp;&nbsp;{"خيارات"|gettext}</button>
  <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <span class="caret"></span>
    <span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu">
      {if !empty($module)}{include file="`$module`/templates/actionbtns.tpl"}{/if}
      {section loop=$actionbtns name=op}
      {if $actionbtns[op].name eq "order" OR $actionbtns[op].hidden eq 2}{continue}{/if}
      <li><a class="{if $actionbtns[op].ajax eq 1}do-ajax"
  {elseif $actionbtns[op].ajax eq 2}open-ajax"
  {elseif $actionbtns[op].ajax eq 3}do-ajax with-confirm"
  {elseif $actionbtns[op].ajax eq 4}do-ajax with-hide"
  {elseif $actionbtns[op].ajax eq 5}quick-form"
  {elseif $actionbtns[op].ajax eq 6}quick-form"
  {elseif $actionbtns[op].name eq "delete"}delete-ajax"{else}"{/if} {if $actionbtns[op].name eq "printable" || $actionbtns[op].ajax eq 7}target="_blank"{/if} title="{$actionbtns[op].title}" href="{$CPURL}/{if !empty($actionbtns[op].refmodule)}{$actionbtns[op].refmodule}/{else}{$cumodule.name}/{/if}{$actionbtns[op].name}/{if !empty($cusubaction)}{$cusubaction}/{/if}{if empty($actionbtns[op].param)}?id={else}?{$actionbtns[op].param}={/if}{$result.id}">
          <i class="fa fa-{$actionbtns[op].icon}"></i>&nbsp;&nbsp;{$actionbtns[op].title|gettext}</a>
      </li>
      {/section}
  </ul>
</div>
