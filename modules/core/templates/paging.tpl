{if isset($paging)}
<div class="pull-right ltr">
  <select class="inline-flex ajaxSelect" style="width: 50px" onchange="window.location = '{$paging.pageurl}1&perpage=' + this.value">
    {section name=op start=50 step=50 loop=550}
      <option {if $smarty.get.perpage eq $smarty.section.op.index}selected="selected"{/if}>{$smarty.section.op.index}</option>
    {/section}
  </select>
  <div class="inline-flex btn btn-default">{"عدد النتائج"|gettext} {$paging.result} {"نتيجة مقسمة علي"|gettext} {$paging.pages} {"صفحة"|gettext}</div>
</div>
<br><br>
<div class="pull-right ltr">
  {if $paging.pages gt 1}
  <ul class="pagination margin-none inline-flex">
    <li {if $paging.callpage eq 1}class="disabled"{/if}><a href="{$paging.pageurl}1">&laquo;</a></li>
      {section name=page start=$paging.start loop=$paging.callpage+5}
        {if $smarty.section.page.index+1 le $paging.pages}
        <li {if $paging.callpage eq $smarty.section.page.index+1}class="active"{/if}>
          <a href="{$paging.pageurl}{$smarty.section.page.index+1}">{$smarty.section.page.index+1}</a></li>
        {/if}
      {/section}
    <li {if $paging.callpage eq $paging.pages}class="disabled"{/if}><a href="{$paging.pageurl}{$paging.pages}">&raquo;</a></li>
  </ul>
  <div class="inline-flex" style="width:150px">
    <div class="input-group">
      <input placeholder="{"رقم الصفحة"|gettext}" value="{$paging.page}" type="text" class="form-control" />
      <span class="input-group-btn">
        <button type="button" class="btn btn-primary" onclick="window.location = '{$paging.pageurl}' + $('.pageToGoNo').val()">{"إذهب"|gettext}</button>
      </span>
    </div>
  </div>
  {/if}
</div>
{/if}