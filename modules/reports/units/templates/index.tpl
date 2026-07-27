<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"فترة زمنية"|gettext}</label>
      <div class="col-md-3">
        <input type="text" name="fdate" value="{$smarty.get.fdate}" class="form-control datepicker" autocomplete="off" placeholder="{"من"|gettext}" required />
      </div>
      <div class="col-md-3">
        <input type="text" name="todate" value="{$smarty.get.todate}" class="form-control datepicker" autocomplete="off" placeholder="{"إلي"|gettext}" required />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-2">{"تقرير سنوي"|gettext}</label>
      <div class="col-md-3">
        <select name="year" required>
            {section name=op start=$start_year loop=$start_year+20}
              <option {if $smarty.section.op.index eq $smarty.get.year}selected="selected"{/if}>{$smarty.section.op.index}</option>
            {/section}
        </select>
      </div>
    </div>
    <div class="row col-md-12">
      <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث وتنقيح"|gettext}</button>
    </div>
    <div class="clearfix"></div>
  </form>
</div>

{if !empty($results)}

<!-- Chart Graph -->
<div class="row chartGraphs">
  <div class="col-md-8">
    <!-- Icon only Tabs -->
    <div class="relativeWrap">
      <div class="widget widget-4 widget-tabs-icons-only margin-bottom-none">
        <div class="widget-head" style="border-top-left-radius:0">
          <h4 class="heading"><i class="fa fa-line-chart"></i> {"تقرير زمني"|gettext}</h4>
          <ul class="pull-right">
            <li>{"استعراض"|gettext}</li>
            <li class="withicon active">
              <span data-toggle="tab" data-target="#stat-income"><i class="fa fa-money"></i> {"عقارات"|gettext}</span>
            </li>
            <li class="withicon">
              <span data-toggle="tab" data-target="#stat-delays"><i class="fa fa-gavel"></i>{"عقود إيجار"|gettext}</span>
            </li>
          </ul>
          <div class="clearfix"></div>
        </div>
        <div class="widget-body">
          <div class="tab-content stat-tab-content">
            <div id="stat-income" class="tab-pane active box-generic">
              <canvas id="horizontalYearBar" width="400" height="200"></canvas>
            </div>
            <div id="stat-delays" class="tab-pane box-generic">
              <canvas id="horizontalYearBar2" width="400" height="200"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- // Icon only Tabs END -->
  </div>
</div>
<!-- Chart Graph -->

<div class="row">
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر العقارات ربحاً"|gettext}</h4>
      <ul class="list-unstyled arfont">
        {foreach $results.top_builds_profit as $record}
        <li class="innerAll half border-bottom">
          <span class="badge badge-success pull-right">{$record.totalmoney}</span> {$record.title} <span class="label label-default">{$record.location}</span>
        </li>
        {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر العميلين تأخراً في دفع المستحقات"|gettext}</h4>
      <ul class="list-unstyled arfont">
          {foreach $results.top_delay_persons as $record}
            <li class="innerAll half border-bottom">
            {if $record.module eq "rent"}
              {assign var=person value=$record.renter|json_decode:true}
              <span class="badge badge-default pull-right">{$record.totalmoney}</span> {$person.name[0]} <span class="label label-warning">{$record.title2}</span>
              <span class="label label-danger">{"ايجار"|gettext}</span>
            {else}
              {assign var=person value=$record.buyers|json_decode:true}
              <span class="badge badge-default pull-right">{$record.totalmoney}</span> {$person.name[0]} <span class="label label-warning">{$record.title}</span>
              <span class="label label-danger">{"بيع"|gettext}</span>
            {/if}
        </li>
        {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر العميلين مديونية"|gettext}</h4>
      <ul class="list-unstyled arfont">
          {foreach $results.top_credit_persons as $record}
            <li class="innerAll half border-bottom">
            {if $record.module eq "rent"}
              {assign var=person value=$record.renter|json_decode:true}
              <span class="badge badge-default pull-right">{$record.totalmoney}</span> {$person.name[0]} <span class="label label-warning">{$record.title2}</span>
              <span class="label label-danger">{"ايجار"|gettext}</span>
            {else}
              {assign var=person value=$record.buyers|json_decode:true}
              <span class="badge badge-default pull-right">{$record.totalmoney}</span> {$person.name[0]} <span class="label label-warning">{$record.title}</span>
              <span class="label label-danger">{"بيع"|gettext}</span>
            {/if}
        </li>
        {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر العقارات مصاريفاً"|gettext}</h4>
      <ul class="list-unstyled arfont">
          {foreach $results.top_builds_expenses as $record}
            <li class="innerAll half border-bottom">
              <span class="badge badge-danger pull-right">{$record.totalmoney}</span> {$record.title} <span class="label label-default">{$record.location}</span>
            </li>
          {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر العقارات إيجاراً"|gettext}</h4>
      <ul class="list-unstyled arfont">
          {foreach $results.top_builds_rented as $record}
            <li class="innerAll half border-bottom">
              <span class="badge badge-success pull-right">{$record.totalcount}</span> {$record.title} <span class="label label-default">{$record.location}</span>
            </li>
          {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر العقارات إشغالاً"|gettext}</h4>
      <ul class="list-unstyled arfont">
          {foreach $results.builds_busy_rate as $record}
            <li class="innerAll half border-bottom">
              <span class="badge badge-success pull-right">{$record.busyRate}%</span> {$record.title} <span class="label label-default">{$record.location}</span>
            </li>
          {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر العملاء في عدد مرات الإيجار"|gettext}</h4>
      <ul class="list-unstyled arfont">
          {foreach $results.top_renters as $record}
            <li class="innerAll half border-bottom">
              <span class="badge badge-success pull-right">{$record.totalcount}</span> #{$record.id} - {$record.name}
            </li>
          {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر الملاك امتلاكاً للعقارات"|gettext}</h4>
      <ul class="list-unstyled arfont">
          {foreach $results.top_owners as $record}
            <li class="innerAll half border-bottom">
              <span class="badge badge-default pull-right">{$record.totalcount}</span> #{$record.id} - {$record.name}
            </li>
          {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر الأحياء طلباً للإيجار"|gettext}</h4>
      <ul class="list-unstyled arfont">
          {foreach $results.top_districts_inrent as $record}
            <li class="innerAll half border-bottom">
              <span class="badge badge-success pull-right">{$record.totalcount}</span> {$record.name}
            </li>
          {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->
  <!-- Start of stat block -->
  <div class="col-md-4">
    <div class="widget widget-body-white padding-none">
      <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"أكثر المدن طلباً للإيجار"|gettext}</h4>
      <ul class="list-unstyled arfont">
          {foreach $results.top_cities_inrent as $record}
            <li class="innerAll half border-bottom">
              <span class="badge badge-success pull-right">{$record.totalcount}</span> {$record.name}
            </li>
          {/foreach}
      </ul>
    </div>
  </div>
  <!-- End of stat block -->

</div>
{/if}
