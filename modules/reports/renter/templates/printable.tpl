<table class="table table-condensed table-striped table-primary table-vertical-center">
  <tr>
    <th>{"المبلغ"|gettext}</th>
    <td>{$data.amount|clean}</td>
  </tr>
  <tr>
    <th>{"الحالة"|gettext}</th>
    <td>{if $data.gone eq 1}{"مرحل"|gettext}{else}{"غير مرحل"|gettext}{/if}</td>
  </tr>
  {if $data.gone eq 1}
  <tr>
    <th>{"تاريخ تحويله"|gettext}</th>
    <td>{$data.gone_date|ardate:false:false}</td>
  </tr>
  {/if}
  <tr>
    <th>{"تاريخ استلامه"|gettext}</th>
    <td>{$data.paydate|ardate:false:false}</td>
  </tr>
  <tr>
    <th>{"قيمة"|gettext}</th>
    <td>{$data.paymenttype|gettext|clean}</td>
  </tr>
  <tr>
    <th>{"العقار"|gettext}</th>
    <td>{$data.build|clean}</td>
  </tr>
  <tr>
    <th>{"المالك"|gettext}</th>
    <td>
      {section name=op loop=count($data.owner.id)}
      <p>{$data.owner.name[op]}</p>
      {/section}
    </td>
  </tr>
</table>
