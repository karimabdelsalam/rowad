<div class="btn-group pull-right gotomenu-dropdown">
  {if !empty($gotomenus)}
    <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
      <span class="fa fa-align-justify"></span> {"الإنتقال السريع"|gettext} <span class="caret"></span>
    </button>
    <ul class="dropdown-menu pull-right" style="margin-top:0">
      {foreach from=$gotomenus item=gotomenu}
        <li><a href="{$CPURL}/{$gotomenu.module|clean}/{$gotomenu.name|clean}" {if $gotomenu.ajax eq 1}class="open-ajax"{/if}><i class="fa fa-fw fa-{$gotomenu.icon|clean}"></i> {$gotomenu.title|clean}</a></li>
        {/foreach}
    </ul>
  {/if}
</div>
<div class="clearfix"></div>