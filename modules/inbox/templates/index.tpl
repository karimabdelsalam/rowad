<div class="filter-bar">
    <form action="" method="get" class="margin-none form-inline">
        <div class="form-group col-md-3 padding-none">
            <label>{"العنوان"|gettext}</label>
            <div class="input-group">
                <input type="text" name="subject" value="{$smarty.get.subject}" class="form-control" />
            </div>
        </div>
        <div class="form-group col-md-3 padding-none">
            <label>{"الاسم"|gettext}</label>
            <div class="input-group">
                <input type="text" name="name" value="{$smarty.get.name}" class="form-control" />
            </div>
        </div>
        <div class="form-group col-md-3 padding-none">
            <label>{"البريد"|gettext}</label>
            <div class="input-group">
                <input type="text" name="email" value="{$smarty.get.email}" class="form-control" />
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
        <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title}</h4>
    </div>
    <form action="{$CPURL}/{$cumodule.name}/" method="get">
    <div class="widget-body table-responsive">
        <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
            <thead>
                <tr>
                    <th style="width: 1%;" class="uniformjs">
                        <input type="checkbox" class="checkAll" />
                    </th>
                    <th class="center"></th>
                    <th class="center">{"الحالة"|gettext}</th>
                    <th>{"العنوان"|gettext}</th>
                    <th>{"الاسم"|gettext}</th>
                    <th class="center">{"التاريخ"|gettext}</th>
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
                    <td class="center">{if $result.isread eq 1}<i class="fa fa-circle-o"></i>{else}<i class="fa fa-circle"></i>{/if}</td>
                    <td>
                        <strong>{$result.subject}</strong>
                    </td>
                    <td>{$result.name}</td>
                    <td class="center"><span class="label label-primary">{$result.timepost|ardate}</span></td>
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
