<div class="innerLR">
  <div class="row">
    <div class="col-md-4">
      <!-- Widget-->
      <div class="widget widget-body-white padding-none">
        <h4 class="innerAll bg-gray border-bottom margin-bottom-none border-radius-top"><i class="fa fa-bar-chart-o"></i> {"إحصائيات"|gettext}</h4>
        <ul class="list-unstyled arfont">
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.rentContracts}</span> {"عدد عقود الإيجار"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.sellContracts}</span> {"عدد عقود البيع"|gettext}
          </li>
          <li class="innerAll half border-bottom">
            <span class="badge badge-default pull-right">{$stats.loans|number_format:2} {$config.currency}</span> {"اجمالي المدفوعات المتأخرة"|gettext}
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
      {include file="./widget_rent_contratcs.tpl"}
      {include file="./widget_payments.tpl"}
    </div>
    <!-- //End Col -->
  </div>
  <!-- End Row -->
</div>
