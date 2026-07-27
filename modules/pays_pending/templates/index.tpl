{if $smarty.get.quick_form eq 0}
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
    <input type="hidden" name="type" value="{$smarty.get.type}">
    <div class="form-group">
      <label class="col-md-2">{"اسم مالك العقار"|gettext}</label>
      <div class="col-md-4">
        <select class="buildownerAjaxSearch noSelect2" name="ownerid" style="width:100%">
            {if $smarty.get.ownerid}
              <option value="{$smarty.get.ownerid}">{$owner.fname} {$owner.fathname} {$owner.lname} {$owner.famname}</option>
            {/if}
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
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"الى"|gettext}" />
      </div>
    </div>
    <div class="row col-md-12">
      <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث وتنقيح"|gettext}</button>
    </div>
    <div class="clearfix"></div>
  </form>
</div>
{/if}
{/if}

{if empty($smarty.get.quick_form)}
<!-- Chart Graph -->
<div class="row chartGraphs">
  <div class="col-md-12">
    <div class="widget widget-body-white">
      <div class="row">
        <div class="col-md-offset-2 col-md-8">
          <canvas id="horizontalBar" width="300" height="300"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Chart Graph -->
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
  <form action="{$CPURL}/{$cumodule.name}/" method="get" target="_blank">
    <div class="widget-body">
      {include file="./data_table.tpl"}
      {if $smarty.get.quick_form eq 0}
      <div class="pull-left checkboxs_actions hide-2">
        {include file="core/templates/module_multi_actions.tpl"}
      </div>
      {include file="core/templates/paging.tpl"}
      {/if}
      <div class="clearfix"></div>
    </div>
  </form>
</div>
