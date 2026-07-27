<!-- Widget -->
<div class="widget overflow-hidden">
    <h4 class="innerAll bg-gray border-bottom margin-bottom-none"><i class="fa fa-hourglass-end"></i> {"عقود منتهية او قاربت على الانتهاء"|gettext}</h4>
    <div class="widget-body">
        <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
          <thead>
            <tr>
              <th class="center">{"م"|gettext}</th>
              <th class="center">{"اسم المستأجر"|gettext}</th>
              <th class="center">{"بداية العقد"|gettext}</th>
              <th class="center">{"نهاية العقد"|gettext}</th>
              <th class="center">{"قيمة العقد"|gettext}</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$endedContracts item=result}
              <tr>
                <td class="center">{$result.id}</td>
                <td class="center">
                  {section name=op loop=count($result.buyer.id)}
                  <span class="label label-info marginTB">{$result.buyer.name[op]}</span><br />
                  {/section}
                </td>
                <td class="center"><span class="label label-default">{$result.startdate|ardate:false:false}</span></td>
                <td class="center"><span class="label label-danger">{$result.enddate|ardate:false:false}</span></td>
                <td class="center"><span class="label label-primary">{$result.rentvalue}</span></td>
              </tr>
            {foreachelse}
              <tr class="warning"><td class="center" colspan="20">{"لا يوجد اية عقود منتهية"|gettext}</td></tr>
              {/foreach}
          </tbody>
        </table>
    </div>
</div>
<!-- //Widget -->
