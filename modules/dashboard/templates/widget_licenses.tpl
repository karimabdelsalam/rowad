<!-- Widget -->
<div class="widget overflow-hidden">
    <h4 class="innerAll bg-gray border-bottom margin-bottom-none"><i class="fa fa-paperclip"></i> {"تراخيص قاربت على الإنتهاء"|gettext}</h4>
    <div class="widget-body">
        <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
          <thead>
            <tr>
              <th class="center" style="width:80px">{"م"|gettext}</th>
              <th class="center">{"اسم المستند"|gettext}</th>
              <th class="center">{"رقم المستند"|gettext}</th>
              <th class="center">{"تاريخ الانتهاء"|gettext}</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$endedLicenses item=result}
              <tr>
                <td class="center">{$result.id}</td>
                <td>
                  <strong>{$result.title}</strong>
                </td>
                <td class="center"><span class="label label-default">{$result.number}</span></td>
                <td class="center"><span class="label label-primary">{$result.expiredate|ardate:false:false}</span></td>
              </tr>
            {foreachelse}
              <tr class="warning"><td class="center" colspan="20">{"لا يوجد رخص قاربت على الإنتهاء"|gettext}</td></tr>
            {/foreach}
          </tbody>
        </table>
    </div>
</div>
<!-- //Widget -->
