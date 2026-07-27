<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"نوع الخطاب"|gettext}</label>
      <div class="col-md-3">
        <select name="type" class="form-control">
          <option value="">{"اختر نوع الخطاب"|gettext}</option>
          {foreach $types as $type}
          <option value="{$type.name}" {if $smarty.get.type eq $type.name}selected="selected"{/if}>{$type.title|gettext}</option>
          {/foreach}
        </select>
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2 control-label">{"اسم المستأجر"|gettext}</label>
      <div class="col-md-4">
        <select class="buildrenterAjaxSearch noSelect2" name="renterid" style="width:100%" required>
          {if !empty($smarty.get.renterid)}<option value="{$smarty.get.renterid}">{$renter.fullname}</option>{/if}
        </select>
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2 control-label">{"خاص بالعقار"|gettext}</label>
      <div class="col-md-5">
        <select class="form-control" name="buildid" required>
          {if !empty($smarty.get.buildid)}
            <option value="{$smarty.get.buildid}">{$build}</option>
          {/if}
        </select>
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"تاريخ الخطاب"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder="{"من"|gettext}" />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"إلي"|gettext}" />
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
    <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <form action="{$CPURL}/{$cumodule.name}/" method="get">
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
        <thead>
          <tr>
            <th style="width: 1%;" class="uniformjs">
              <input type="checkbox" class="checkAll" />
            </th>
            <th class="center" style="width:80px">{"رقم الخطاب"|gettext}</th>
            <th class="center">{"نوع الخطاب"|gettext}</th>
            <th class="center">{"تاريخه"|gettext}</th>
            <th class="center">{"المستفيد"|gettext}</th>
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
                <strong>{$result.type_title|gettext}</strong>
              </td>
              <td class="center"><span class="label label-primary">{$result.postdate|ardate:false:false}</span></td>
              <td class="center"><span class="label label-success">
                {if $result.type eq "official"}
                  {$result.official.subject|clean}
                {elseif $result.type eq "cancel" OR $result.type eq "finish" OR $result.type eq "rentraise"}
                  {$result.renter.fullname}
                {elseif $result.type eq "review"}
                  {$result.review.provider|clean}
                {/if}
                </span>
              </td>
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
