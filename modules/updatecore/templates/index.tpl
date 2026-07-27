<div class="widget widget-body-white" style="width:700px;margin:150px auto;">
  <div class="widget-head">
    <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
  </div>
  <div class="widget-body padding-none">
    {if $update gt 0}
      <div class="jumbotron margin-none bg-white">
        <div class="alert alert-warning">
          <button type="button" class="close" data-dismiss="alert">×</button>
          {"تحذير: لا تنسى اخذ نسخة احتياطية كاملة من بيانات النظام قبل البدأ في عملية التحديث ."|gettext}
        </div>
        <h2 class="separator bottom">{"يوجد تحديث جديد !"|gettext}</h2>
        <p>{"النظام يعمل بالأصدارة"|gettext} <span class="badge badge-danger">{$config.version|clean|number_format:2}</span> {"وننصحك بالتحديث الى الأصدار الأخير"|gettext} <span class="badge badge-success">{$update|clean|number_format:2}</span></p>
        {$changelog|clean}
        <p class="margin-none innerT"><a href="{$CPURL}/{$cumodule.name|clean}/update" class="btn btn-success btn-lg">{"بدأ التحديث الآن"|gettext}</a></p>
      </div>
      <p class="margin-none innerT pull-left"><a href="{$CPURL}" class="btn btn-primary btn-lg">{"الرجوع إلي الرئيسية"|gettext}</a></p>
    {elseif isset($update)}
      <div class="jumbotron margin-none bg-white">
        <h2 class="separator bottom">{"لا يوجد تحديثات !"|gettext}</h2>
        <p>{"النظام يعمل بالأصدارة"|gettext} {$config.version|clean} {"ولا يوجد تحديثات جديدة تم اصدارها ."|gettext}</p>
      </div>
      <p class="margin-none innerT pull-left"><a href="{$CPURL}" class="btn btn-primary btn-lg">{"الرجوع إلي الرئيسية"|gettext}</a></p>
    {else}
      <div class="jumbotron margin-none bg-white">
        <h2 class="separator bottom">{"جاري تحديث النظام"|gettext}</h2>
      </div>
      <p class="margin-none innerT pull-left"><a href="{$CPURL}/{$cumodule.name|clean}/index" class="btn btn-primary btn-lg">{"الرجوع"|gettext}</a></p>
    {/if}
  </div>
</div>
