<div id="actionGroupMenu" class="hide2 inline-block">
{if !empty($actionbtns)}
  <label class="strong inline-flex">
    <select name="Action" class="ajaxSelect" style="width: 190px" required>
      <option value="">{"رجاء تحديد المطلوب"|gettext}</option>
      {foreach from=$actionbtns item=actionbtn}
        {if $actionbtn.actiongroup eq 1}
          <option value="{$actionbtn.name}">{$actionbtn.title|gettext|clean}</option>
        {/if}
      {/foreach}
    </select>
  </label>
  <button type="submit" class="checkMultiBoxes btn btn-primary"><i class="fa fa-check-circle"></i> {"تنفيذ"|gettext}</button>
{/if}
</div>
<button type="button" id="printOTF" class="btn btn-primary inline-block"><i class="fa fa-print"></i> {"طباعة جميع النتائج"|gettext}</button>
<button type="button" id="exportOTF" class="btn btn-primary inline-block"><i class="fa fa-file-excel-o"></i> {"تصدير جميع النتائج Excel"|gettext}</button>
