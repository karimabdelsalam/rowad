{if $smarty.get.quick_form eq 0}
  <div class="navbar-blocks">
    <a href="{$CPURL}/pays_processed/index/?{$params}" class="btn btn-success"><i class="fa fa-4x fa-calendar-check-o"></i> <p class="text-larger marginTB">{"محصلة"|gettext}</p></a>
    <a href="{$CPURL}/pays_pending/index/?type=pending&{$params}" class="btn btn-primary"><i class="fa fa-4x fa-calendar"></i><p class="text-larger marginTB">{"مجدولة"|gettext}</p></a>
    <a href="{$CPURL}/pays_pending/index/?type=late&{$params}" class="btn btn-danger"><i class="fa fa-4x fa-calendar-times-o"></i> <p class="text-larger marginTB">{"متأخرات"|gettext}</p></a>
  </div>
{/if}

<div class="widget widget-body-white">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <form action="{$CPURL}/{$cumodule.name}/" method="get" target="_blank">
    <div class="widget-body">
      {include file="./data_table.tpl"}
      {if $smarty.get.quick_form eq 0}
      <div class="pull-left checkboxs_actions hide-2">
        {include file="core/templates/module_multi_actions.tpl"}
      </div>
      {include file="core/templates/paging.tpl"}
      {/if}
      <div class="clearfix"></div>
    </div>
  </form>
</div>
