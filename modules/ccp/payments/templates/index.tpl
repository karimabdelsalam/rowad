{if $smarty.get.quick_form eq 0}
{if !empty($build)}
<div class="filter-bar">
  <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseBuildContainer"><i class="fa fa-plus-square-o"></i> {"بيانات العقار"|gettext}</h4>
  <div class="separator"></div>
  <div class="innerAll collapseBuildContainer" style="display:none">
    {include file="core/templates/build_info.tpl" quick=1}
  </div>
</div>
{else}
<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <input type="hidden" name="type" value="{$smarty.get.type}">
    <div class="form-group">
      <label class="col-md-2">{"خاص بالعقار"|gettext}</label>
      <div class="col-md-3">
        <select class="form-control" name="buildid">
          <option value="">{"غير محدد"|gettext}</option>
            {foreach $builds as $build}
              <option value="{$build.id}" {if $smarty.get.buildid eq $build.id}selected="selected"{/if}>{$build.title} {if !empty($build.location)} [{$build.location}]{/if}</option>
            {/foreach}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"تاريخ الإستحقاق"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder="{"من"|gettext}" />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"الى"|gettext}" />
      </div>
    </div>
    <div class="row col-md-12">
      <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث وتنقيح"|gettext}</button>
    </div>
    <div class="clearfix"></div>
  </form>
</div>
{/if}
{/if}

{if $smarty.get.quick_form eq 0}
<div class="navbar-blocks">
  <a href="{$CPURL}/{$cumodule.name}/{$cuaction.name}/?type=gone&{$params}" class="btn btn-success"><i class="fa fa-4x fa-calendar-check-o"></i> <p class="text-larger marginTB">{"محصلة"|gettext}</p></a>
  <a href="{$CPURL}/{$cumodule.name}/{$cuaction.name}/?type=pending&{$params}" class="btn btn-primary"><i class="fa fa-4x fa-calendar"></i><p class="text-larger marginTB">{"مجدولة"|gettext}</p></a>
  <a href="{$CPURL}/{$cumodule.name}/{$cuaction.name}/?type=late&{$params}" class="btn btn-danger"><i class="fa fa-4x fa-calendar-times-o"></i> <p class="text-larger marginTB">{"متأخرات"|gettext}</p></a>
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
