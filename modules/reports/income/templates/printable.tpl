<table class="table table-condensed table-striped table-primary table-vertical-center">
  <tr>
    <th>{"رقم السند"|gettext}</th>
    <td>{$data.id|clean}</td>
  </tr>
  <tr>
    <th>{"استلمت من"|gettext}</th>
    <td>{$data.fromTypeName|gettext}</td>
  </tr>
  <tr>
  {if $data.from_type eq "owner"}
    <th>{"اسم المالك"|gettext}</th>
    <td>
      {foreach $companyowners as $companyowner}
        {if $companyowner.id|in_array:$data.from_id}
        <p>{$companyowner.fname} {$companyowner.fathname} {$companyowner.lname} {$companyowner.famname}</p>
        {/if}
      {/foreach}
    </td>
  {elseif $data.from_type eq "employee"}
    <th>{"اسم الموظف"|gettext}</th>
    <td>{$data.from.fname} {$data.from.fathname} {$data.from.lname} {$data.from.famname}</td>
  {elseif $data.from_type eq "buildowner"}
    <th>{"اسم مالك المنشأة"|gettext}</th>
    <td>{$data.from.fname} {$data.from.fathname} {$data.from.lname} {$data.from.famname}</td>
  </tr>
  <tr>
    <th>{"خاص بالعقار"|gettext}</th>
    <td>{$data.build}</td>
  {elseif $data.from_type eq "buildrenter"}
    <th>{"اسم المستأجر"|gettext}</th>
    <td>{$data.from.fname} {$data.from.fathname} {$data.from.lname} {$data.from.famname}</td>
  </tr>
  <tr>
    <th>{"خاص بالعقار"|gettext}</th>
    <td>{$data.build}</td>
  {elseif $data.from_type eq "govorg"}
    <th>{"اسم الجهة الحكومية"|gettext}</th>
    <td>{$data.from.title}</td>
  {elseif $data.from_type eq "citizen"}
    <th>{"اسم المواطن"|gettext}</th>
    <td>{$data.from.title}</td>
  {elseif $data.from_type eq "comorg"}
    <th>{"اسم الجهة التجارية"|gettext}</th>
    <td>{$data.from.title}</td>
  {/if}
  </tr>
  <tr>
    <th>{"المبلغ"|gettext}</th>
    <td>{$data.amount|clean}</td>
  </tr>
  <tr>
    <th>{"طريقة الاستلام"|gettext}</th>
    <td>
      {if $data.pay_method eq "cash"}{"نقداً"|gettext}
      {elseif $data.pay_method eq "cheque"}{"شيك"|gettext}
      {elseif $data.pay_method eq "bank"}{"تحويل بنكي"|gettext}
      {/if}
    </td>
  </tr>
  {if $data.pay_method eq "cheque"}
  <tr>
    <th>{"رقم الشيك"|gettext}</th>
    <td>{$data.cheque.no|clean}</td>
  </tr>
  <tr>
    <th>{"اسم البنك"|gettext}</th>
    <td>
      {foreach $banks as $bank}
      {if $data.cheque.bankid eq $bankaccount.id}{$bank.title|clean}{/if}
      {/foreach}
    </td>
  </tr>
  <tr>
    <th>{"تاريخ الشيك"|gettext}</th>
    <td>{$data.cheque.issuedate|ardate:false:false}</td>
  </tr>
  {elseif $data.pay_method eq "bank"}
  <tr>
    <th>{"تاريخ التحويل"|gettext}</th>
    <td>{$data.bank.issuedate|ardate:false:false}</td>
  </tr>
  <tr>
    <th>{"رقم الحساب المحول إليه"|gettext}</th>
    <td>
      {foreach $bankaccounts as $bankaccount}
      {if $data.bank.tobankid eq $bankaccount.id}{$bankaccount.bank}: {$bankaccount.fullname}{/if}
      {/foreach}
    </td>
  </tr>
  <tr>
    <th>{"اسم الحساب المحول منه"|gettext}</th>
    <td>{$data.bank.fromname|clean}</td>
  </tr>
  <tr>
    <th>{"رقم مرجع التحويل"|gettext}</th>
    <td>{$data.bank.refno|clean}</td>
  </tr>
  <tr>
    <th>{"اسم البنك المحول منه"|gettext}</th>
    <td>
      {foreach $banks as $bank}
      {if $data.bank.frombankid eq $bankaccount.id}{$bank.title|clean}{/if}
      {/foreach}
    </td>
  </tr>
  <tr>
    <th>{"رقم الحساب المحول منه"|gettext}</th>
    <td>{$data.bank.fromno|clean}</td>
  </tr>
  {/if}
  <tr>
    <th>{"نوع الإيراد"|gettext}</th>
    <td>{$data.type|clean}</td>
  </tr>
  <tr>
    <th>{"تاريخ القبض"|gettext}</th>
    <td>{$data.issueddate|ardate:false:false}</td>
  </tr>
  <tr>
    <th>{"السبب"|gettext}</th>
    <td>{$data.reason|nl2br|clean}</td>
  </tr>
</table>
