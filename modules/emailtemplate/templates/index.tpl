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
                    <th>{"العنوان"|gettext}</th>
                    <th class="center">{"اشعار SMS"|gettext}</th>
                    <th class="center">{"اشعار بريدي"|gettext}</th>
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
                        <strong>{$result.subject|gettext|clean}</strong>
                    </td>
                    <td class="center">{if $result.sms_active eq 1}<i class="fa fa-check-circle btn-success btn-stroke"></i>{else}<i class="fa fa-times-circle"></i>{/if}</td>
                    <td class="center">{if $result.active eq 1}<i class="fa fa-check-circle btn-success btn-stroke"></i>{else}<i class="fa fa-times-circle"></i>{/if}</td>
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
        <div class="clearfix"></div>
    </div>
    </form>
</div>
