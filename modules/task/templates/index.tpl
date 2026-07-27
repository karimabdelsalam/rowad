<div class="filter-bar">
    <form action="" method="get" class="margin-none form-inline">
        <div class="form-group col-md-3 padding-none">
            <label>{"موجهة إلي"|gettext}</label>
            <div class="input-group col-md-8">
                <select class="form-control" name="tomanagerid">
                    <option value="0">{"جميع المدراء"|gettext}</option>
                {foreach from=$managers item=manager}
                    <option value="{$manager.id}" {if $manager.id eq $smarty.get.tomanagerid}selected="selected"{/if}>{$manager.name|clean}</option>
                {/foreach}
                </select>
            </div>
        </div>
        <div class="form-group col-md-3 padding-none">
            <label>{"الحالة"|gettext}</label>
            <div class="input-group col-md-8">
                <select class="form-control" name="done">
                    <option value="0">{"غير محدد"|gettext}</option>
                    <option value="1" {if $smarty.get.done eq 1}selected="selected"{/if}>{"مهام منتهية"|gettext}</option>
                    <option value="2" {if $smarty.get.done eq 2}selected="selected"{/if}>{"مهام مفتوحة"|gettext}</option>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث وتنقيح"|gettext}</button>
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
        <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
            <thead>
                <tr>
                    <th style="width: 1%;" class="uniformjs">
                        <input type="checkbox" class="checkAll" />
                    </th>
                    <th>{"المهمة"|gettext}</th>
                    <th class="center">{"الحالة"|gettext}</th>
                    <th class="center">{"المسئول"|gettext}</th>
                    <th class="center">{"من"|gettext}</th>
                    <th class="center no-print" style="width: 150px;"></th>
                </tr>
            </thead>
            <tbody>
            {foreach from=$results item=result}
                <tr class="selectable">
                    <td class="center uniformjs">
                        <input type="checkbox" name="ids[]" value="{$result.id}" />
                    </td>
                    <td>
                        <strong class="hiddentrim">{$result.content|clean|truncate:200}</strong>
                    </td>
                    <td class="center">{if $result.done eq 1}<i class="fa fa-check-circle btn-success btn-stroke"></i>{else}<i class="fa fa-times-circle"></i>{/if}</td>
                    <td class="center"><span class="label label-default label-stroke">{$result.manager|clean}</span></td>
                    <td class="center"><span class="badge badge-primary">{$result.timepost|Period}</span></td>
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
