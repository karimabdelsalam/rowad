<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"الاسم"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="title" value="{$smarty.get.title}" class="form-control" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2 control-label">{"فئة العقد"|gettext}</label>
      <div class="col-md-4">
        <select name="cycle" class="form-control">
            <option value="">{"اختر فئة العقد"|gettext}</option>
            <option value="sale" {if $smarty.get.cycle eq "sale"}selected="selected"{/if}>{"عقد بيع"|gettext}</option>
            <option value="day" {if $smarty.get.cycle eq "day"}selected="selected"{/if}>{"عقد إيجار يومي"|gettext}</option>
            <option value="month" {if $smarty.get.cycle eq "month"}selected="selected"{/if}>{"عقد إيجار شهري"|gettext}</option>
            <option value="year" {if $smarty.get.cycle eq "year"}selected="selected"{/if}>{"عقد إيجار سنوي"|gettext}</option>
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
      <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
        <thead>
          <tr>
            <th style="width: 1%;" class="uniformjs">
              <input type="checkbox" class="checkAll" />
            </th>
            <th class="center" style="width:80px">{"م"|gettext}</th>
            <th class="center">{"الاسم"|gettext}</th>
            <th class="center">{"فئة العقد"|gettext}</th>
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
                <strong>{$result.title}</strong>
              </td>
              <td class="center"><span class="label label-primary">
              {if $result.cycle eq "sale"}{"عقد بيع"|gettext}
              {elseif $result.cycle eq "day"}{"عقد إيجار يومي"|gettext}
              {elseif $result.cycle eq "month"}{"عقد إيجار شهري"|gettext}
              {elseif $result.cycle eq "year"}{"عقد إيجار سنوي"|gettext}
              {/if}</span>
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
