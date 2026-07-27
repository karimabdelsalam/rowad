<table class="table table-condensed table-striped table-primary table-vertical-center">
  <tr>
    <th>{"قيمة الضريبة"|gettext}</th>
    <td>{$data.amount|clean} {$config.currency}</td>
  </tr>
  <tr>
    <th>{"التاريخ"|gettext}</th>
    <td>{$data.gone_date|ardate:false:false}</td>
  </tr>
  <tr>
    <th>{"النوع"|gettext}</th>
    <td>{$data.transtype|gettext|clean}</td>
  </tr>
    {if !empty($data.paytype)}
      <tr>
        <th>{"تفاصيل الدفعة"|gettext}</th>
        <td>{$data.paytype|gettext|clean}</td>
      </tr>
      <tr>
        <th>{"تاريخ استحقاقها"|gettext}</th>
        <td>{$data.paymentdate|ardate:false:false}</td>
      </tr>
      <tr>
        <th>{"العقار"|gettext}</th>
        <td>{$data.build|clean}</td>
      </tr>
    {/if}
</table>
