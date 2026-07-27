<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"اسم"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fullname" value="{$smarty.get.fullname}" class="form-control" />
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"النوع"|gettext}</label>
      <div class="col-md-2">
        <select name="type" class="form-control">
          <option value="">{"غير محدد"|gettext}</option>
          <option value="person" {if $smarty.get.type eq "person"}selected="selected"{/if}>{"افراد"|gettext}</option>
          <option value="company" {if $smarty.get.type eq "company"}selected="selected"{/if}>{"شركات"|gettext}</option>
          <option value="organization" {if $smarty.get.type eq "organization"}selected="selected"{/if}>{"مؤسسات"|gettext}</option>
          <option value="government" {if $smarty.get.type eq "government"}selected="selected"{/if}>{"دوائر حكومية"|gettext}</option>
        </select>
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"نوع العقود"|gettext}</label>
      <div class="col-md-2">
        <select name="contract" class="form-control">
          <option value="">{"غير محدد"|gettext}</option>
          <option value="rent" {if $smarty.get.contract eq "rent"}selected="selected"{/if}>{"ايجار"|gettext}</option>
          <option value="sell" {if $smarty.get.contract eq "sell"}selected="selected"{/if}>{"بيع"|gettext}</option>
        </select>
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"رقم الجوال"|gettext}</label>
      <div class="col-md-2">
        <input type="number" name="mobile" value="{$smarty.get.mobile}" minlength="{$config.mobile_length}" maxlength="14" placeholder="{$config.phone_code}xxxxxxxxx" class="form-control ltr" />
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"بريد إلكتروني"|gettext}</label>
      <div class="col-md-3">
        <input type="email" name="email" value="{$smarty.get.email}" class="form-control ltr" />
      </div>
    </div>
    <div class="form-group hide2">
      <label class="col-md-2">{"تاريخ الإنشاء"|gettext}</label>
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
            <th class="center">{"م"|gettext}</th>
            <th class="center">{"الاسم"|gettext}</th>
            {if $smarty.get.contract neq "sell"}
            <th class="center">{"عقود ايجار"|gettext}</th>
            {/if}
            {if $smarty.get.contract eq "sell"}
            <th class="center">{"عقود بيع"|gettext}</th>
            {/if}
            <th class="center">{"رقم الجوال"|gettext}</th>
            <th class="center">{"تاريخ الإنشاء"|gettext}</th>
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
                <strong>{$result.fname} {$result.fathname} {$result.lname} {$result.famname}{if $result.type neq "person"} [{$result.company}]{/if}</strong>
              </td>
              {if $smarty.get.contract neq "sell"}
                <td class="center"><span class="label label-warning">{$result.rentCount}</span></td>
              {/if}
              {if $smarty.get.contract eq "sell"}
                <td class="center"><span class="label label-warning">{$result.sellCount}</span></td>
              {/if}
              <td class="center"><span class="label label-default">{$result.mobile}</span></td>
              <td class="center"><span class="label label-primary">{$result.timepost|ardate:false:false}</span></td>
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
