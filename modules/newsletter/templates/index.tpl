<div class="filter-bar">
    <form action="" method="get" class="margin-none form-inline">
        <div class="form-group col-md-5 padding-none">
            <label>{"البريد الإلكتروني"|gettext}</label>
            <div class="input-group">
                <input type="text" name="email" value="{$smarty.get.email|clean}" class="form-control" />
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
                    <th class="center"></th>
                    <th>{"البريد الإلكتروني"|gettext}</th>
                    <th class="center">{"تاريخ الانضمام"|gettext}</th>
                    <th class="center no-print" style="width: 150px;"></th>
                </tr>
            </thead>
            <tbody>
            {foreach from=$results item=result}
                <tr class="selectable">
                    <td class="center uniformjs">
                        <input type="checkbox" name="ids[]" value="{$result.id}" />
                    </td>
                    <td class="center">{$result.id}</td>
                    <td>
                        <strong>{$result.email|clean}</strong>
                    </td>
                    <td class="center"><span class="label label-primary">{$result.timepost|ardate}</span></td>
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
