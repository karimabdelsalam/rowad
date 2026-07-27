<div class="widget widget-body-white">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <form action="{$CPURL}/{$cumodule.name}/{$cuaction.name}" class="form-horizontal margin-none form-ajax" method="post">
    <div class="widget-body">
      <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
        <thead>
          <tr>
            <th class="center" style="width: 80px;">{"اسم الوحدة"|gettext}</th>
            <th class="center" style="width: 150px;">{"ترقيم يبدأ من"|gettext}</th>
          </tr>
        </thead>
        <tbody>
            <tr>
              <td>
                <strong>{"المستأجرين"|gettext}</strong>
              </td>
              <td><input class="form-control" name="serial[buyer]" value="{$serial.buyer}" type="number" min="1" required /></td>
            </tr>
            <tr>
              <td>
                <strong>{"ملاك العقارات"|gettext}</strong>
              </td>
              <td><input class="form-control" name="serial[owner]" value="{$serial.owner}" type="number" min="1" required /></td>
            </tr>
            <tr>
              <td>
                <strong>{"العقارات"|gettext}</strong>
              </td>
              <td><input class="form-control" name="serial[building]" value="{$serial.building}" type="number" min="1" required /></td>
            </tr>
            <tr>
              <td>
                <strong>{"عقود البيع"|gettext}</strong>
              </td>
              <td><input class="form-control" name="serial[sell_contracts]" value="{$serial.sell_contracts}" type="number" min="1" required /></td>
            </tr>
            <tr>
              <td>
                <strong>{"عقود الإيجار"|gettext}</strong>
              </td>
              <td><input class="form-control" name="serial[rent_contracts]" value="{$serial.rent_contracts}" type="number" min="1" required /></td>
            </tr>
            <tr>
              <td>
                <strong>{"سندات القبض"|gettext}</strong>
              </td>
              <td><input class="form-control" name="serial[statement_in]" value="{$serial.statement_in}" type="number" min="1" required /></td>
            </tr>
            <tr>
              <td>
                <strong>{"سندات الصرف"|gettext}</strong>
              </td>
              <td><input class="form-control" name="serial[statement_out]" value="{$serial.statement_out}" type="number" min="1" required /></td>
            </tr>
            <tr>
              <td>
                <strong>{"الفواتير"|gettext}</strong>
              </td>
              <td><input class="form-control" name="serial[invoices]" value="{$serial.invoices}" type="number" min="1" required /></td>
            </tr>
            <tr>
              <td>
                <strong>{"الخطابات والاشعارات"|gettext}</strong>
              </td>
              <td><input class="form-control" name="serial[letters]" value="{$serial.letters}" type="number" min="1" required /></td>
            </tr>
        </tbody>
      </table>
      <div class="separator"></div>
      <div class="innerAll border-top">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ إعدادات الترقيم"|gettext}</button>
      </div>
    </div>
  </form>
</div>
