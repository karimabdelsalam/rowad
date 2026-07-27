<!-- Widget -->
<div class="widget overflow-hidden">
    <h4 class="innerAll bg-gray border-bottom margin-bottom-none"><i class="fa fa-briefcase"></i> {"هويات موظفين قاربت على الإنتهاء"|gettext}</h4>
    <div class="widget-body">
        <table class="table table-condensed table-striped table-primary table-vertical-center checkboxs">
          <thead>
            <tr>
              <th class="center">{"م"|gettext}</th>
              <th class="center">{"الاسم"|gettext}</th>
              <th class="center">{"رقم الهوية"|gettext}</th>
              <th class="center">{"الجنسية"|gettext}</th>
              <th class="center">{"تاريخ الإنشاء"|gettext}</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$endedEmployees item=result}
              <tr>
                <td class="center">{$result.id}</td>
                <td>
                  <strong>{$result.fname} {$result.fathname} {$result.lname} {$result.famname}</strong>
                </td>
                <td class="center"><span class="label label-default">{$result.idnum}</span></td>
                <td class="center"><span class="label label-warning">{$result.country}</span></td>
                <td class="center"><span class="label label-primary">{$result.timepost|ardate:false:false}</span></td>
              </tr>
            {foreachelse}
              <tr class="warning"><td class="center" colspan="20">{"لا يوجد هويات قاربت على الإنتهاء"|gettext}</td></tr>
              {/foreach}
          </tbody>
        </table>
    </div>
</div>
<!-- //Widget -->
