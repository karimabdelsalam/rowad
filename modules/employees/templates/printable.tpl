<table>
  <tr>
    <th>{"الاسم"|gettext}</th>
    <td>{$data.fname|clean} {$data.fathname|clean} {$data.lname|clean} {$data.famname|clean}</td>
  </tr>
  <tr>
    <th>{"الجنسية"|gettext}</th>
    <td>{$data.country}</td>
  </tr>
  <tr>
    <th>{"نوع الهوية"|gettext}</th>
    <td>{if $data.idtype eq "idcard"}{"هوية وطنية"|gettext}{elseif $data.idtype eq "passport"}{"جواز سفر"|gettext}{elseif $data.idtype eq "visa"}{"إقامة"|gettext}{/if}</td>
  </tr>
  <tr>
    <th>{"رقم الهوية"|gettext}</th>
    <td>{$data.idnum|clean}</td>
  </tr>
  <tr>
    <th>{"مصدرها"|gettext}</th>
    <td>{$data.idsource|clean}</td>
  </tr>
  <tr>
    <th>{"المؤهل"|gettext}</th>
    <td>{$data.education|clean}</td>
  </tr>
  <tr>
    <th>{"رقم الجوال"|gettext}</th>
    <td>{$data.mobile|clean}</td>
  </tr>
  <tr>
    <th>{"رقم هاتف المنزل"|gettext}</th>
    <td>{$data.homephone|clean}</td>
  </tr>
  <tr>
    <th>{"العنوان"|gettext}</th>
    <td>{$data.address1|clean}<br />{$data.address2|clean}</td>
  </tr>
  <tr>
    <th>{"الراتب الشهري"|gettext}</th>
    <td>{$data.salary|clean}</td>
  </tr>
  <tr>
    <th>{"جوال آخر"|gettext}</th>
    <td>{$data.mobile2|clean}</td>
  </tr>
</table>