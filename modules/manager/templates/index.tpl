<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"الاسم"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="name" value="{$smarty.get.name}" class="form-control" />
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"البريد الإلكتروني"|gettext}</label>
      <div class="col-md-5">
        <input type="email" name="email" value="{$smarty.get.email}" class="form-control ltr" />
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"اسم المستخدم"|gettext}</label>
      <div class="col-md-4">
        <input type="text" name="username" value="{$smarty.get.username}" class="form-control" />
      </div>
    </div>
    <div class="row col-md-12">
      <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث وتنقيح"|gettext}</button>
      <button type="button" class="btn btn-default moreSearchOptions"><i class="fa fa-sort-desc"></i></button>
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
                    <th>{"الاسم"|gettext}</th>
                    <th>{"البريد الإلكتروني"|gettext}</th>
                    <th class="center">{"اسم المستخدم"|gettext}</th>
                    <th class="center">{"تاريخ الإنشاء"|gettext}</th>
                    <th class="center">{"الحالة"|gettext}</th>
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
                        <strong>{$result.name|clean}</strong>
                    </td>
                    <td class="center">{$result.email|clean}</td>
                    <td class="center">{$result.username|clean}</td>
                    <td class="center"><span class="label label-primary">{$result.joindate|ardate}</span></td>
                    <td class="center">{if $result.active eq 1}<span class="label label-success">{"نشط"|gettext}</span>{else}<span class="label label-danger">{"غير نشط"|gettext}</span>{/if}</td>
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
