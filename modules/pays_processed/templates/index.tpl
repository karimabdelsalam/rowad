{if !empty($build)}
<div class="filter-bar">
  <h4 class="innerAll bg-container margin-none pointer" data-collapse="collapseBuildContainer"><i class="fa fa-plus-square-o"></i> {"بيانات العقار"|gettext}</h4>
  <div class="separator"></div>
  <div class="innerAll collapseBuildContainer" style="display:none">
    {include file="core/templates/build_info.tpl" quick=1}
  </div>
</div>
{else}
<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"اسم المالك"|gettext}</label>
      <div class="col-md-3">
        <select class="buildownerAjaxSearch noSelect2" name="ownerid" style="width:100%">
          {if $smarty.get.ownerid}<option value="{$smarty.get.ownerid}">{$from.fullname}</option>{/if}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"خاص بالعقار"|gettext}</label>
      <div class="col-md-5">
        <select class="buildAjaxSearch noSelect2" name="buildid" style="width:100%">
            {if !empty($smarty.get.buildid)}
              <option value="{$smarty.get.buildid}">{$buildtitle}</option>
            {/if}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"اسم العميل"|gettext}</label>
      <div class="col-md-3">
        <select class="buyerSearch noSelect2" name="buyerid" style="width:100%">
            {if $smarty.get.buyerid}<option value="{$smarty.get.buyerid}">{$buyer.fullname}</option>{/if}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"تاريخ الإستلام"|gettext}</label>
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
{/if}

{if $smarty.get.quick_form eq 0}
  <div class="navbar-blocks">
    <a href="{$CPURL}/pays_processed/index/?{$params}" class="btn btn-success"><i class="fa fa-4x fa-calendar-check-o"></i> <p class="text-larger marginTB">{"محصلة"|gettext}</p></a>
    <a href="{$CPURL}/pays_pending/index/?type=pending&{$params}" class="btn btn-primary"><i class="fa fa-4x fa-calendar"></i><p class="text-larger marginTB">{"مجدولة"|gettext}</p></a>
    <a href="{$CPURL}/pays_pending/index/?type=late&{$params}" class="btn btn-danger"><i class="fa fa-4x fa-calendar-times-o"></i> <p class="text-larger marginTB">{"متأخرات"|gettext}</p></a>
  </div>
{/if}

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
            <th class="center">{"المبلغ"|gettext}</th>
            <th class="center">{"تاريخ الإستحقاق"|gettext}</th>
            <th class="center">{"تاريخ تحويله"|gettext}</th>
            <th class="center">{"قيمة"|gettext}</th>
            <th class="center">{"المالك"|gettext}</th>
            <th class="center">{"العقار"|gettext}</th>
            <th class="center">{"العميل"|gettext}</th>
            <th class="center">{"الربح"|gettext}</th>
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
              <td class="center"><span class="label label-primary">{$result.amount}</span></td>
              <td class="center"><span class="label label-default">{$result.paydate|ardate:false:false}</span></td>
              <td class="center"><span class="label label-default">{$result.gone_date|ardate:false:false}</span></td>
              <td class="center">
                <span class="label label-warning">{$result.paymenttype|gettext}</span> <a href="{$CPURL}/{$result.module}/edit?id={$result.contractid}" target="_blank"><span class="label label-warning"><i class="fa fa-link"></i></span></a>
                  {if $result.invoiceid gt 0}
                    <a href="{$CPURL}/invoices/printable?id={$result.invoiceid}" data-toggle="tooltip" title="{"فاتورة"|gettext}" target="_blank"><span class="label label-default"><i class="fa fa-newspaper-o"></i></span></a>
                  {/if}
                  {if $result.statementid gt 0}
                    <a href="{$CPURL}/statementsin/edit?id={$result.statementid}" data-toggle="tooltip" title="{"سند قبض"|gettext}" class="quick-form"><span class="label label-default"><i class="fa fa-file-text-o"></i></span></a>
                  {/if}
                  {if $result.statoutid gt 0}
                    <a href="{$CPURL}/statementsout/edit?id={$result.statoutid}" data-toggle="tooltip" title="{"سند صرف"|gettext}" class="quick-form"><span class="label label-default"><i class="fa fa-file-o"></i></span></a>
                  {/if}
              </td>
              <td class="">
                {section name=op loop=count($result.owner.id)}
                  <span class="label label-info marginTB" data-toggle="tooltip" data-placement="right" title="{$result.owner.name[op]}">
                      {$result.ownerinfo[op].fname} {$result.ownerinfo[op].fathname}
                  </span><br />
                {/section}
              </td>
              <td class="center"><span class="label label-danger">{$result.build}</span></td>
              <td class="">
                  {section name=op loop=count($result.relatedto.name)}
                    <span class="label label-info marginTB">
                      {$result.relatedto.name[op]}
                    </span><br />
                  {/section}
              </td>
              <td class="center">
              {if !empty($result.commission)}
                <span class="label label-success" data-toggle="tooltip" data-placement="top" title="{"عمولة المكتب يتحمله المالك"|gettext}">{$result.commission} {$config.currency}</span>
              {/if}
              {if !empty($result.commission2)}
                <span class="label label-warning" data-toggle="tooltip" data-placement="top" title="{"عمولة المكتب يتحمله العميل"|gettext}">{$result.commission2} {$config.currency}</span>
              {/if}
              </td>
              <td class="text-left center no-print">
                  {include file="core/templates/module_actions.tpl" module=$cumodule.name}
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
