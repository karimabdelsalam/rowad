<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <input type="hidden" name="buildid" value="{$smarty.get.buildid}" />
    <div class="form-group">
      <label class="col-md-2">{"المسمى"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="title" value="{$smarty.get.title}" class="form-control" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"النوع"|gettext}</label>
      <div class="col-md-2">
        <select name="catid" class="form-control">
            <option value="">{"غير محدد"|gettext}</option>
          {foreach $categories as $category}
            <option value="{$category.id}" {if $category.id eq $smarty.get.catid}selected="selected"{/if}>{$category.name}</option>
          {/foreach}
        </select>
      </div>
    </div>
    <div class="row col-md-12">
      <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث وتنقيح"|gettext}</button>
    </div>
    <div class="clearfix"></div>
  </form>
</div>
<div class="widget widget-body-white">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <form action="{$CPURL}/{$cumodule.name}/" method="get">
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center">
        <thead>
          <tr>
            <th class="center" style="width:80px">{"م"|gettext}</th>
            <th>{"المسمى"|gettext}</th>
            <th class="center">{"النوع"|gettext}</th>
            <th class="center">{"التاريخ"|gettext}</th>
            <th class="center no-print" style="width: 150px;"></th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$results item=result}
            <tr class="selectable">
              <td class="center">{$result.id}</td>
              <td>
                <strong>{$result.title}</strong>
              </td>
              <td class="center"><span class="label label-primary">{$result.name}</span></td>
              <td class="center"><span class="label label-default">{$result.issued_date}</span></td>
              <td class="text-left center no-print">
                {include file="core/templates/module_actions.tpl"}
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
