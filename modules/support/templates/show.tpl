<form action="{$CPURL}/{$cumodule.name}/{$cuaction.name}" class="form-horizontal margin-none form-ajax" method="post">
  <div class="widget">
    <div class="widget-head flex-center">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
      {if $data.read_state eq 0}
      <a href="{$CPURL}/{$cumodule.name}/read/?id={$data.id}" class="btn btn-xs btn-success instantReq" data-load="{"جارى المعالجة"|gettext}" data-success="{"تم غلق الطلب"|gettext}"><i class="fa fa-check-circle"></i> {"غلق الطلب"|gettext}</a>
      {else}
        <a href="{$CPURL}/{$cumodule.name}/unread/?id={$data.id}" class="btn btn-xs btn-default instantReq" data-load="{"جارى المعالجة"|gettext}" data-success="{"تم فتح الطلب"|gettext}"><i class="fa fa-envelope-open"></i> {"فتح الطلب"|gettext}</a>
      {/if}
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"الكود"|gettext}</label>
            <div class="col-md-3">
              {if $data.read_state eq 1}
                <span class="label label-success">{"تم حل الطلب"|gettext}</span>
              {else}
                <span class="label label-default">{"مفتوح"|gettext}</span>
              {/if}
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"الكود"|gettext}</label>
            <div class="col-md-3">
                <span class="label label-warning">{$data.code}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"مرسل الطلب"|gettext}</label>
            <div class="col-md-3">
                {if $data.ownerid gt 0}
                  <a href="{$CPURL}/owner/edit/?id={$data.ownerid}" class="quick-form">{$data.ofname|clean} {$data.ofathname|clean}</a>
                {else}
                  <a href="{$CPURL}/buyer/edit/?id={$data.clientid}" class="quick-form">{$data.bfname|clean} {$data.bfathname|clean}</a>
                {/if}
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"عنوان الطلب"|gettext}</label>
            <div class="col-md-4">
                {$data.subject|clean}
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"نوع الطلب"|gettext}</label>
            <div class="col-md-3">
              <span class="label label-primary">{$data.type|coregate:"supportTypes"}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"مرتبط بالعقار"|gettext}</label>
            <div class="col-md-4">
              <a href="{$CPURL}/building/edit/?id={$data.buildid}" class="quick-form">{$data.buildTitle|clean}</a>
            </div>
          </div>
        </div>
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"التفاصيل"|gettext}</label>
            <div class="col-md-6" style="min-height: 250px">
                {$data.message|clean|nl2br}
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"مرفقات"|gettext}</label>
            <div class="col-md-3">
                {if !empty($data.file)}
                  <a href="{$smarty.const.MEDIAURL}/{$data.file}" class="btn btn-default" target="_blank"><i class="fa fa-external-link"></i> {"عرض المرفق"|gettext}</a>
                {else}
                  <button type="button" class="btn btn-default">{"لا يوجد"|gettext}</button>
                {/if}
            </div>
          </div>
        </div>
      </div>
      <div class="submit-block">
        <button type="button" class="btn btn-primary" onclick="history.back()"><i class="fa fa-chevron-right"></i> {"الرجوع الى قائمة الطلبات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
