<div class="filter-bar">
    <form action="" method="get" class="margin-none form-inline">
        <div class="form-group col-md-4 padding-none">
            <label>{"العنوان"|gettext}</label>
            <div class="input-group">
                <input type="text" name="title" value="{$smarty.get.title|clean}" class="form-control" />
            </div>
        </div>
        <div class="form-group col-md-4 padding-none">
            <label>{"مكان الظهور"|gettext}</label>
            <div class="input-group col-md-8">
                <select class="form-control" name="positionid">
                    <option value="0">{"جميع الاماكن"|gettext}</option>
                {foreach from=$pagepositions item=position}
                    <option value="{$position.id}" {if $position.id eq $smarty.get.positionid}selected="selected"{/if}>{$position.position|clean}</option>
                {/foreach}
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث"|gettext}</button>
        </div>
        <div class="clearfix"></div>
    </form>
</div>
<div class="widget widget-body-white">
    <div class="widget-head">
        <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <form action="{$CPURL}/{$cumodule.name|clean}/" method="get">
    <div class="widget-body table-responsive">
        <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs js-table-sortable">
            <thead>
                <tr>
                    <th style="width: 1%;" class="uniformjs">
                        <input type="checkbox" class="checkAll" />
                    </th>
                    <th class="center" style="width: 50px;"></th>
                    <th>{"العنوان"|gettext}</th>
                    <th class="center">{"الحالة"|gettext}</th>
                    <th class="center">{"صفحات فرعية"|gettext}</th>
                    <th class="center">{"مكان الظهور"|gettext}</th>
                    <th class="center">{"عدد الزيارات"|gettext}</th>
                    <th class="center no-print" style="width: 150px;"></th>
                </tr>
            </thead>
            <tbody>
            {foreach from=$results item=result}
                <tr class="selectable" id="menuSortOrder{$result.id}">
                    <td class="center uniformjs">
                        <input type="checkbox" name="ids[]" value="{$result.id}" />
                    </td>
                    <td class="center js-sortable-handle">
                        <span class="fa fa-arrows move"></span>
                    </td>
                    <td>
                        {if $result.type eq "menu"}
                        <a href="{$CPURL}/{$cumodule.name|clean}/{$cuaction.name|clean}/?parent={$result.id}"><strong>{$result.title|clean}</strong></a>
                        {else}
                        <strong>{$result.title|clean}</strong>
                        {/if}
                    </td>
                    <td class="center">{if $result.active eq 1}<i class="fa fa-check-circle btn-success btn-stroke"></i>{else}<i class="fa fa-times-circle"></i>{/if}</td>
                    <td class="center"><span class="fa fa-fw fa-file-text"></span> {if empty($result.pagecount)}{"لا يوجد"|gettext}{else}<a href="{$CPURL}/{$cumodule.name|clean}/{$cuaction.name|clean}/?parent={$result.id}">{$result.pagecount|clean} {"صفحة"|gettext}</a>{/if}</td>
                    <td class="center"><span class="label label-default label-stroke">{$result.position|clean}</span></td>
                    <td class="center"><span class="badge badge-primary">{$result.read_counter}</span></td>
                    <td class="text-left center no-print">
                        {include file="core/templates/module_actions.tpl"}
                    </td>
                </tr>
            {foreachelse}
                <tr class="warning"><td class="center" colspan="20">{"عفواً...لا يوجد اي نتائج للعرض"|gettext}</td></tr>
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
