{if !empty($build)}
<div class="filter-bar">
  <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseBuildContainer"><i class="fa fa-plus-square-o"></i> {"بيانات العقار"|gettext}</h4>
  <div class="separator"></div>
  <div class="innerAll collapseBuildContainer" style="display:none">
    {include file="core/templates/build_info.tpl" quick=1}
  </div>
</div>
{/if}
<div class="widget widget-body-white">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <form action="{$CPURL}/{$cumodule.name}/" method="get">
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center">
        <thead>
          <tr>
            <th class="center">{"م"|gettext}</th>
            <th class="center">{"اسم المالك"|gettext}</th>
            {if empty($build)}
            <th class="center">{"العقار"|gettext}</th>
            {/if}
            <th class="center">{"تاريخ العقد"|gettext}</th>
            <th class="center">{"قيمة العقد"|gettext}</th>
            <th class="center" style="width: 200px;"></th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$results item=result}
            <tr class="selectable">
              <td class="center">{$result.id}</td>
              <td class="center">
                {section name=op loop=count($result.buyer.id)}
                <span class="label label-info marginTB">{$result.buyer.name[op]}</span><br />
                {/section}
              </td>
              {if empty($build)}
              <td class="center"><span class="label label-warning">{$result.build}</span></td>
              {/if}
              <td class="center"><span class="label label-danger">{$result.postdate|ardate:false:false}</span></td>
              <td class="center"><span class="label label-primary">{$result.total} {$config.currency}</span></td>
              <td class="text-left center no-print">
                {include file="core/templates/module_actions.tpl" module=$cumodule.name}
              </td>
            </tr>
          {foreachelse}
            <tr class="warning"><td class="center" colspan="20">{"لا يوجد بيانات متاحة للعرض الآن"|gettext}</td></tr>
          {/foreach}
        </tbody>
      </table>
      <div class="pull-left checkboxs_actions hide-2">
        {include file="core/templates/module_multi_actions.tpl"}
      </div>
      {include file="core/templates/paging.tpl"}
      <div class="clearfix"></div>
    </div>
  </form>
</div>
