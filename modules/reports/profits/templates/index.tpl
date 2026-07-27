<div class="filter-bar">
  <form action="" method="get" class="form-horizontal innerAll">
    <div class="form-group">
      <label class="col-md-2">{"فترة زمنية"|gettext}</label>
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
  <form action="{$CPURL}/{$cumodule.name}/" method="get">
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center">
        <thead>
          <tr>
            <th class="center">{"رصيد الحسابات البنكية"|gettext}</th>
            <th class="center">{"اجمالى الإيرادات"|gettext}</th>
            <th class="center">{"اجمالي المصروفات"|gettext}</th>
            <th class="center">{"الأرباح"|gettext}</th>
            <th class="center">{"الضرائب"|gettext}</th>
            <th class="center">{"صافي أرباح المؤسسة"|gettext}</th>
          </tr>
        </thead>
        <tbody>
          <tr class="selectable">
            <td class="center"><span class="label label-primary">{$result.bankcredits-$result.bankdebits}</span></td>
            <td class="center"><span class="label label-success">{$result.statement_in}</span></td>
            <td class="center"><span class="label label-danger">{$result.statement_out}</span></td>
            <td class="center"><span class="label label-success">{$result.off_profits}</span></td>
            <td class="center"><span class="label label-danger">{$result.taxes}</span></td>
            <td class="center"><span class="label label-success">{$result.off_profits-$result.taxes}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </form>
</div>
