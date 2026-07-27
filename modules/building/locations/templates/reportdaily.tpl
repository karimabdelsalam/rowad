<table>
  <tr>
    <th colspan="50" class="center" style="font-size: 22px;background-color: #fed54d">{"تقرير"|gettext} {$results[0].m_location} {"شهر"|gettext} {$smarty.now|date_format:"%I/%Y"}</th>
  </tr>
  <tr>
    <th rowspan="2">{"رقم الوحدة"|gettext}</th>
    <th rowspan="2">{"الموقع"|gettext}</th>
    <th rowspan="2">{"الحالة"|gettext}</th>
    <th rowspan="2">{"الاسم"|gettext}</th>
    <th rowspan="2">{"الهاتف"|gettext}</th>
    <th rowspan="2">{"قيمة الإيجار"|gettext}</th>
    <th colspan="2">{"الوضع المالي"|gettext}</th>
    <th colspan="3">{"الدفعة المستحقة"|gettext}</th>
    <th rowspan="2">{"الملاحظات"|gettext}</th>
  </tr>
  <tr>
    <th>{"مدفوع"|gettext}</th>
    <th>{"متبقي"|gettext}</th>
    <th>{"التاريخ"|gettext}</th>
    <th>{"المبلغ"|gettext}</th>
    <th>{"الحالة"|gettext}</th>
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
            <td>{$result.contract_info.allpaid}</td>
            <td>{$result.contract_info.remain}</td>
              {foreach from=$result.payments item=payment}
                <td>{$payment.paydate}</td>
                <td>{$payment.amount-$payment.paidamount}</td>
                  {if $payment.gone eq 0 AND strtotime($payment.paydate) < time()}
                    <td style="background-color: #ff4646; color: #fff">{"غير مدفوع"|gettext}</td>
                  {else}
                    <td>{"مدفوع"|gettext}</td>
                  {/if}
              {foreachelse}
                <th colspan="3"></th>
              {/foreach}
            <td>{$result.contract_info.notes}</td>
          {/if}
      </tr>
    {/foreach}
  <tr>
    <th style="background-color:#fed54d">{"الإجمالي"|gettext}</th>
    <th colspan="5"></th>
    <th style="background-color:#fed54d">{$results[0].total_sum.paid}</th>
    <th style="background-color:#fed54d">{$results[0].total_sum.remain}</th>
    <th></th>
    <th style="background-color:#fed54d">{$results[0].total_sum.collect}</th>
    <th colspan="3"></th>
  </tr>
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
