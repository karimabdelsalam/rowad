<table>
  <tr>
    <th colspan="50" class="center" style="font-size: 22px;background-color: #fed54d">{"تقرير شامل"|gettext}</th>
  </tr>
  <tr>
    <th rowspan="2">{"رقم الوحدة"|gettext}</th>
    <th rowspan="2">{"الموقع"|gettext}</th>
    <th rowspan="2">{"الحالة"|gettext}</th>
    <th rowspan="2">{"الاسم"|gettext}</th>
    <th rowspan="2">{"الهاتف"|gettext}</th>
    <th rowspan="2">{"قيمة الإيجار"|gettext}</th>
    <th colspan="2">{"الوضع المالي"|gettext}</th>
    <th colspan="2">{"تاريخ العقد"|gettext}</th>
    <th rowspan="2">{"ملاحظات"|gettext}</th>
      {section name=fo loop=$results[0].max_payments}
        <th colspan="2">{"استحقاق الدفعة"|gettext} {$smarty.section.fo.iteration}</th>
      {/section}
  </tr>
  <tr>
    <th>{"مدفوع"|gettext}</th>
    <th>{"متبقي"|gettext}</th>
    <th>{"من"|gettext}</th>
    <th>{"الى"|gettext}</th>
      {section name=fo loop=$results[0].max_payments}
        <th>{"التاريخ"|gettext}</th>
        <th>{"المبلغ"|gettext}</th>
      {/section}
  </tr>
    {foreach from=$results item=result}
      <tr>
        <td>{$result.title}</td>
        <td>{$result.loc_details}</td>
        <td class="center">
            {if !empty($result.freein)}
              <span class="label label-danger">{"غير متاح حتي"|gettext} {$result.freein|ardate:false:false}</span>
            {elseif $result.available eq 1 AND $result.type eq "sale"}
              <span class="label label-success">{"غير مباعة"|gettext}</span>
            {elseif $result.available eq 1}
              <span class="label label-success">{"شاغر"|gettext}</span>
            {elseif $result.available eq 0 AND $result.type eq "sale"}
              <span class="label label-danger">{"تم بيعه"|gettext}</span>
            {else}
              <span class="label label-success">{"شاغر"|gettext}</span>
            {/if}
        </td>
          {if empty($result.contract_info)}
            <td></td>
            <td></td>
            <td>{$result.rentvalue} {$config.currency}</td>
            <td colspan="50"></td>
          {else}
            <td>{$result.contract_info.buyer.profile[0].fullname}</td>
            <td>{$result.contract_info.buyer.profile[0].mobile}</td>
              {if !empty($result.contract_info.rentvalue)}
                <td>{$result.contract_info.rentvalue} {$config.currency}</td>
              {else}
                <td>-</td>
              {/if}
            <td>{$result.contract_info.allpaid} {$config.currency}</td>
            <td>{$result.contract_info.remain} {$config.currency}</td>
              {if !empty($result.contract_info.startdate)}
                <td>{$result.contract_info.startdate|ardate:false:false}</td>
                <td>{$result.contract_info.enddate|ardate:false:false}</td>
                <td>{$result.contract_info.notes}</td>
              {else}
                <td>-</td>
                <td>-</td>
                <td>-</td>
              {/if}
              {foreach from=$result.payments item=payment}
                  {if $payment.gone eq 0 AND strtotime($payment.paydate) < time()}
                    <td style="background-color: #ff4646; color: #fff">{$payment.paydate}</td>
                  {else}
                    <td>{$payment.paydate}</td>
                  {/if}
                <td>{$payment.amount-$payment.paidamount} {$config.currency}</td>
              {/foreach}
          {/if}
      </tr>
    {/foreach}
</table>

{literal}
  <style>
    table {
      width: 100%;
      text-align: center;
    }
    th{
      width:auto;
      text-align: center;
    }
  </style>
{/literal}
