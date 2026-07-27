<div class="panel panel-danger" style="width:700px;margin:150px auto;">
  <div class="panel-heading">
    <h3 class="panel-title"><i class="fa fa-info-circle"></i> {"رسالة نظام"|gettext}</h3>
  </div>
  <div class="panel-body">
    {$message}
    {if $case eq 1}<br /><br />
    <button type="button" class="btn btn-danger" onclick="window.location='javascript:history.back()'"><i class="fa fa-arrow-circle-left"></i> {"الرجوع للخلف"|gettext}</button>
    {elseif strval($case) eq "logout"}<br /><br />
    <a type="button" class="btn btn-default" href="{$CPURL}/login/logout"><i class="fa fa-sign-out"></i> {"تسجيل الخروج"|gettext}</a>
    <button type="button" class="btn btn-danger" onclick="window.location='javascript:history.back()'"><i class="fa fa-arrow-circle-left"></i> {"الرجوع للخلف"|gettext}</button>
    {/if}
  </div>
</div>