<table class="table table-condensed table-striped table-primary table-vertical-center">
  <tr>
    <th style="width:15%">{"اسم العقار"|gettext}</th>
    <td>{$build.title|clean}</td>
  </tr>
  <tr>
    <th>{"فئة العقار"|gettext}</th>
    <td>{$build.buildcat_name|clean}</td>
  </tr>
  {if !empty($build.buyercat_name)}
  <tr>
    <th>{"فئة المستأجر"|gettext}</th>
    <td>{$build.buyercat_name|clean}</td>
  </tr>
  {/if}
  <tr>
    <th>{"المدينة"|gettext}</th>
    <td>{$build.city_name|clean}</td>
  </tr>
  <tr>
    <th>{"الموقع علي الخريطة"|gettext}</th>
    <td><a href="https://www.google.com/maps?q=loc:{$build.location.latitude},{$build.location.longitude}" target="_blank">اضغط هنا</a></td>
  </tr>
  <tr>
    <th>{"رقم اللوحة"|gettext}</th>
    <td>{$build.platno|clean}</td>
  </tr>
  <tr>
    <th>{"رقم المخطط"|gettext}</th>
    <td>{$build.planno|clean}</td>
  </tr>
  <tr>
    <th>{"رقم المبني"|gettext}</th>
    <td>{$build.buildno|clean}</td>
  </tr>
  <tr>
    <th>{"وصف الموقع"|gettext}</th>
    <td>{$build.details|clean|nl2br}</td>
  </tr>
  {if $quick neq 1}
  <tr>
    <th>{"وثائق التملك"|gettext}</th>
    <td>
      <table style="text-align: center;width: auto;">
        <tr>
          <th>{"نوع الوثيقة"|gettext}</th>
          <th>{"رقمها"|gettext}</th>
          <th>{"مصدرها"|gettext}</th>
          <th>{"تاريخها"|gettext}</th>
        </tr>
        {section name=op loop=count($build.deed.no)}
            <tr>
              <td>{$build.deed.type_name[op]}</td>
              <td>{$build.deed.no[op]}</td>
              <td>{$build.deed.source[op]}</td>
              <td>{$build.deed.date[op]}</td>
            </tr>
        {/section}
      </table>
    </td>
  </tr>
  <tr>
    <th>{"عدادات المرافق"|gettext}</th>
    <td>
      <table style="text-align: center;width: auto;">
        <tr>
          <th>{"نوع الخدمة"|gettext}</th>
          <th>{"نوع الإشتراك"|gettext}</th>
          <th>{"رقم الإشتراك"|gettext}</th>
          <th>{"رقم السداد"|gettext}</th>
          <th>{"كود السداد"|gettext}</th>
        </tr>
        {section name=op loop=count($build.meters.no)}
            <tr>
              <td>{$build.meters.type_name[op]}</td>
              <td>{$build.meters.owner_type_name[op]}</td>
              <td>{$build.meters.no[op]}</td>
              <td>{$build.meters.pay_no[op]}</td>
              <td>{$build.meters.pay_code[op]}</td>
            </tr>
        {/section}
      </table>
    </td>
  </tr>
  <tr>
    <th>{"نوع العقار"|gettext}</th>
    <td>{if $build.type eq "rent"}{"تأجير"|gettext}{else}{"بيع"|gettext}{/if}</td>
  </tr>
  {/if}
  {if empty($hide_owners_info)}
  <tr>
    <th>{"المالك"|gettext}</th>
    <td>
      {section name=op loop=count($build.owner.id)}
        <p>{$build.owner.name[op]} <span class="label label-default">{"يمتلك"|gettext}</span> {$build.owner.share[op]}% <span class="label label-default">{"يتحمل"|gettext}</span> {$build.owner.outgoings[op]}%</p>
      {/section}
    </td>
  </tr>
  {/if}
  {if $quick neq 1 AND $show_contract neq 1}
  {if $build.type eq "rent"}
  <tr>
    <th>{"طريقة التأجير"|gettext}</th>
    <td>{if $build.period eq "year"}{"سنوي"|gettext}{else}{"شهري"|gettext}{/if}</td>
  </tr>
  <tr>
    <th>{"طريقة دفع الإيجار"|gettext}</th>
    <td>{if $build.payment eq "month"}{"إيجار شهري"|gettext}{elseif $build.payment eq "midyear"}{"إيجار نصف سنوي"|gettext}{else}{"إيجار سنوي"|gettext}{/if}</td>
  </tr>
  <tr>
    <th>{"طريقة دفع الإيجار"|gettext}</th>
    <td>{$build.rentcomm|clean}</td>
  </tr>
  <tr>
    <th>{"قيمة الإيجار"|gettext}</th>
    <td>{$build.rentvalue|clean}</td>
  </tr>
  {else}
  <tr>
    <th>{"قيمة الحد"|gettext}</th>
    <td>{$build.max|clean}</td>
  </tr>
  <tr>
    <th>{"قيمة السوم"|gettext}</th>
    <td>{$build.bid|clean}</td>
  </tr>
  <tr>
    <th>{"نسبة عمولة المكتب"|gettext}</th>
    <td>{$build.salecomm|clean} %</td>
  </tr>
  {/if}
  {/if}
  {if $quick neq 1}
  <tr>
    <th>{"عدد القطع"|gettext}</th>
    <td>
      {section name=op loop=count($build.plots.no)}
        <table style="width:100%" class="table table-condensed table-striped table-primary table-vertical-center">
          <tr>
            <th class="center" rowspan="4" style="width: 20%;background: #e9f3fb">
              {"القطعة رقم"|gettext}: {$build.plots.no[op]}<br>
              {"مساحتها"|gettext}ا: {$build.plots.size[op]} {"م2"|gettext}
            </th>
            <td class="center">{"يحدها من الشمال"|gettext}</td>
            <td class="center">
              {if $build.plots.north_type[op] eq "build"}{"جار"|gettext}{else}{"شارع"|gettext}{/if}
            </td>
            <td class="center" style="width: 35%">
              {if $build.plots.north_type[op] eq "street"}
                {$build.plots.north_length[op]} × {$build.plots.north_width[op]} {"م"|gettext}
              {else}
                {$build.plots.north_length[op]} {"م"|gettext}
              {/if}
            </td>
          </tr>
          <tr>
            <td class="center">{"يحدها من الجنوب"|gettext}</td>
            <td class="center">
              {if $build.plots.south_type[op] eq "build"}{"جار"|gettext}{else}{"شارع"|gettext}{/if}
            </td>
            <td class="center" style="width: 35%">
              {if $build.plots.south_type[op] eq "street"}
                {$build.plots.south_length[op]} × {$build.plots.south_width[op]} {"م"|gettext}
              {else}
                {$build.plots.south_length[op]} {"م"|gettext}
              {/if}
            </td>
          </tr>
          <tr>
            <td class="center">{"يحدها من الغرب"|gettext}</td>
            <td class="center">
              {if $build.plots.east_type[op] eq "build"}{"جار"|gettext}{else}{"شارع"|gettext}{/if}
            </td>
            <td class="center" style="width: 35%">
              {if $build.plots.east_type[op] eq "street"}
                {$build.plots.east_length[op]} × {$build.plots.east_width[op]} {"م"|gettext}
              {else}
                {$build.plots.east_length[op]} {"م"|gettext}
              {/if}
            </td>
          </tr>
          <tr>
            <td class="center">{"يحدها من الشرق"|gettext}</td>
            <td class="center">
              {if $build.plots.west_type[op] eq "build"}{"جار"|gettext}{else}{"شارع"|gettext}{/if}
            </td>
            <td class="center" style="width: 35%">
              {if $build.plots.west_type[op] eq "street"}
                {$build.plots.west_length[op]} × {$build.plots.west_width[op]} {"م"|gettext}
              {else}
                {$build.plots.west_length[op]} {"م"|gettext}
              {/if}
            </td>
          </tr>
        </table>
      {/section}
    </td>
  </tr>
  {/if}
</table>
