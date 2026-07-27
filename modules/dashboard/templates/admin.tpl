{if $config.active eq 0}
<div class="alert alert-warning">
  <button type="button" class="close" data-dismiss="alert">×</button>
  {"يرجى العلم ان الخدمة مغلقة الأن والمدراء فقط يمكنهم استخدام الموقع. يمكنك الغاء الغلق من خلال صفحة الاعدادات"|gettext}
</div>
{/if}
<div class="innerLR">
  <div class="row">

    {if $config.skip_guide_widget eq 0}
      {include file="./widget_guide.tpl"}
    {/if}

    <div class="filter-bar">
      <form class="margin-none form-inline">
        <div class="form-group col-md-3 padding-none no-border-space">
          <label>{"عرض النتائج المالية للعام"|gettext}</label>
        </div>
        <div class="form-group col-md-3 padding-none no-border-space">
          <select name="year">
              {section name=op start=$start_year loop=$start_year+20}
                <option {if $smarty.section.op.index eq $smarty.get.year}selected="selected"{/if}>{$smarty.section.op.index}</option>
              {/section}
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> {"بحث"|gettext}</button>
        </div>
        <div class="clearfix"></div>
      </form>
    </div>

    <!-- Icon only Tabs -->
    <div class="relativeWrap">
      <div class="widget widget-4 widget-tabs-icons-only margin-bottom-none">
        <div class="widget-head" style="border-top-left-radius:0">
          <h4 class="heading"><i class="fa fa-line-chart"></i> {"إحصائيات مالية"|gettext}</h4>
          <ul class="pull-right">
            <li>{"استعراض"|gettext}</li>
            <li class="withicon active">
              <span data-toggle="tab" data-target="#stat-all"><i class="fa fa-bar-chart"></i> {"نظرة عامة"|gettext}</span>
            </li>
            <li class="withicon">
              <span data-toggle="tab" data-target="#stat-profits"><i class="fa fa-trophy"></i>{"الأرباح"|gettext}</span>
            </li>
            <li class="withicon">
              <span data-toggle="tab" data-target="#stat-incomes"><i class="fa fa-money"></i>{"الإيرادات"|gettext}</span>
            </li>
            <li class="withicon">
              <span data-toggle="tab" data-target="#stat-outcomes"><i class="fa fa-pie-chart"></i> {"المصروفات"|gettext}</span>
            </li>
          </ul>
          <div class="clearfix"></div>
        </div>
        <div class="widget-body">
          <div class="tab-content stat-tab-content">
            <div id="stat-all" class="tab-pane active box-generic">
              <div id="stat_finance_chart_all" class="flotchart-holder"><div class="alert alert-warning text-center">{"لا يوجد بيانات مالية خلال المدة المحددة"|gettext}</div></div>
            </div>
            <div id="stat-profits" class="tab-pane active box-generic">
              <div id="stat_finance_chart_profits" class="flotchart-holder"><div class="alert alert-warning text-center">{"لا يوجد بيانات مالية خلال المدة المحددة"|gettext}</div></div>
            </div>
            <div id="stat-incomes" class="tab-pane active box-generic">
              <div id="stat_finance_chart_incomes" class="flotchart-holder"><div class="alert alert-warning text-center">{"لا يوجد بيانات مالية خلال المدة المحددة"|gettext}</div></div>
            </div>
            <div id="stat-outcomes" class="tab-pane active box-generic">
              <div id="stat_finance_chart_outcomes" class="flotchart-holder"><div class="alert alert-warning text-center">{"لا يوجد بيانات مالية خلال المدة المحددة"|gettext}</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- // Icon only Tabs END -->

    <div class="col-md-4">
      <!-- Widget-->
      <div class="widget widget-body-white padding-none ">
        <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top">
          <i class="fa fa-globe"></i> {"معلومات النظام"|gettext}
        </h4>
        <ul class="list-unstyled arfont">
          <li class="innerAll half flex-space border-bottom">{"إصدارة النظام"|gettext} <span class="label label-success">{$config.version|clean}</span></li>
          <li class="innerAll half flex-space border-bottom">{"نوع الرخصة"|gettext} <span class="label label-primary">{$config.updatecore.plan.title}</span></li>
          <li class="innerAll half flex-space border-bottom">{"بداية الترخيص"|gettext} <span class="label label-primary">{$config.updatecore.plan.created_time|ardate:false:false}</span></li>
          <li class="innerAll half flex-space border-bottom">{"نهاية الترخيص"|gettext} <span class="label label-primary">{$config.updatecore.plan.format_time|ardate:false:false}</span> </li>
          <li class="innerAll half flex-space border-bottom">{"متبقي"|gettext} <span class="label label-default">{$config.updatecore.plan.format_time|Remain} {"يوم"|gettext}</span> </li>
          <!--<li class="innerAll half flex-space pb-0 mb-0" style="padding-bottom: 0!important;">{"حصة عدد الوحدات"|gettext} <span class="label label-primary">{$stats.unitcount.quota} {"وحدة"|gettext}</span> </li>
          <li class="innerAll half flex-space pt-0 border-bottom">{"متبقي"|gettext} <span class="label label-{$stats.unitcount.color}">{$stats.unitcount.remain} {"وحدة"|gettext}</span> </li>-->
          <li class="innerAll half flex-space pb-0 mb-0" style="padding-bottom: 0!important;">{"حصة مساحة التخزين"|gettext} <span class="label label-primary">{$stats.filesize.quota} {"ميجا"|gettext}</span> </li>
          <li class="innerAll half flex-space border-bottom">{"متبقي"|gettext} <span class="label label-{$stats.filesize.color}">{$stats.filesize.remain} {"ميجا"|gettext}</span> </li>
        </ul>
      </div>
      <!-- //Widget -->
      <!-- Widget-->
      <div class="widget widget-body-white padding-none">
        <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"إحصائيات"|gettext}</h4>
        <ul class="list-unstyled arfont">
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.renters}</span> {"عدد العملاء"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.owners}</span> {"عدد ملاك العقارات"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.builds}</span> {"عدد العقارات"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.freeSaleBuilds}</span> {"عدد العقارات الشاغرة للبيع"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.selledBuilds}</span> {"عدد العقارات المباعة"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.rentedBuilds}</span> {"عدد العقارات المؤجرة"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.freeRentBuilds}</span> {"عدد العقارات الشاغرة للإيجار"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.bankBalance|number_format:2} {$config.currency}</span> {"رصيد الحسابات البنكية"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.outcome|number_format:2} {$config.currency}</span> {"المصروفات"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.income|number_format:2} {$config.currency}</span> {"الإيرادات"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.profit|number_format:2} {$config.currency}</span> {"الأرباح"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.officeOwners}</span> {"عدد الملاك للمنشأة"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.employee}</span> {"عدد الموظفين"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.admins}</span> {"عدد مدراء النظام"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.transactions|number_format:2} {$config.currency}</span> {"إجمالي المبالغ الغير المرحلة"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.goneTransactions|number_format:2} {$config.currency}</span> {"إجمالي المبالغ المرحلة"|gettext}
          </li>
        </ul>
      </div>
      {include file="./widget_notifications.tpl"}
      {include file="./widget_notes.tpl"}
      {include file="./last_login.tpl"}
      <!-- //Widget -->
    </div>
    <!-- //End Col -->
    <div class="col-md-8">
      {include file="./account_transactions.tpl"}
      {include file="./widget_rent_contratcs.tpl"}
      {include file="./widget_payments.tpl"}
      {include file="./widget_licenses.tpl"}
      {include file="./widget_employees.tpl"}
      <a name="tasks"></a>
      {include file="./widget_tasks.tpl"}
    </div>
    <!-- //End Col -->
  </div>
  <!-- End Row -->
</div>
