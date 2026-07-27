<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"اسم المستخدم"|gettext}</label>
      <div class="col-md-4">
        <input type="text" name="username" value="{$smarty.get.username}" class="form-control" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"البحث في"|gettext}</label>
      <div class="col-md-3">
        <select name="moduleid" class="form-control">
          <option value="">{"اختر وحدة"|gettext}</option>
          {foreach $menus as $menue}
            <option value="{$menue.id}" {if $smarty.get.moduleid eq $menue.id}selected="selected"{/if}>{$menue.title}</option>
          {/foreach}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"في تاريخ"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder="{"من"|gettext}" />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"إلي"|gettext}" />
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
  <form action="{$CPURL}/{$cumodule.name}" method="get">
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
        <thead>
          <tr>
            <th style="width: 1%;" class="uniformjs">
              <input type="checkbox" class="checkAll" />
            </th>
            <th class="center" style="width:80px">{"م"|gettext}</th>
            <th class="center">{"تفاصيل الحركة"|gettext}</th>
            <th class="center">{"التوقيت"|gettext}</th>
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
                <span class="label label-success">{$result.manager}</span> <span class="label label-primary">{"قام ب"|gettext}{$result.actiontitle|gettext}</span>{if !empty($result.title)} <span class="label label-default">{$result.title|gettext}</span>{elseif !empty($result.objectid)} <span class="label label-default">{$result.objectid}</span>{/if} <span class="label label-danger">{"في"|gettext}</span> <span class="label label-danger">{$result.modtitle|gettext}</span>
                &nbsp;
                {if $result.action eq "delete" OR empty($result.editaction)}
                <div class="btn-group btn-group-xs"><a href="{$CPURL}/{$result.module}/{$result.indexaction}" target="_blank" class="btn btn-default"><i class="fa fa-link"></i></a></div>
                {elseif $result.objectid eq 0}
                <div class="btn-group btn-group-xs"><a href="{$CPURL}/{$result.module}/{$result.action}" target="_blank" class="btn btn-default"><i class="fa fa-link"></i></a></div>
                {elseif !empty($result.editaction)}
                <div class="btn-group btn-group-xs"><a href="{$CPURL}/{$result.module}/edit/?id={$result.objectid}" target="_blank" class="btn btn-default"><i class="fa fa-link"></i></a></div>
                {/if}
              </td>
              <td class="center"><span class="label label-warning">{$result.timepost|ardate:true:false}</span></td>
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
