<table>
  <tr>
    <th>{"المبلغ"|gettext}</th>
    <td>{$data.amount|clean} {$config.currency}</td>
  </tr>
  <tr>
    <th>{"تاريخ الإستحقاق"|gettext}</th>
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
