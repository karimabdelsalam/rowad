<table>
  <tr>
    <th>{"اسم صاحب الحساب"|gettext}</th>
    <td>{$data.fullname|clean}</td>
  </tr>
  <tr>
    <th>{"اسم البنك"|gettext}</th>
    <td>{$data.bank|clean}</td>
  </tr>
  <tr>
    <th>{"رقم الفرع"|gettext}</th>
    <td>{$data.branchno|clean}</td>
  </tr>
  <tr>
    <th>{"رقم الحساب"|gettext}</th>
    <td>{$data.accno|clean}</td>
  </tr>
  <tr>
    <th>{"رقم الآيبان"|gettext}</th>
    <td>{$data.iban|clean}</td>
  </tr>
</table>