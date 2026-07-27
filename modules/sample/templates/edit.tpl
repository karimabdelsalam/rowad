<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم النموذج"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="title" value="{$data.title|gettext|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <div class="col-md-12">
              <textarea class="form-control pagebuilder" name="content" rows="8">{$data.content|clean}</textarea>
            </div>
          </div>
          {if $data.id neq 1 AND $data.id neq 2}
          <div class="form-group">
            <label class="col-md-2 control-label">{"رأس الصفحة"|gettext}</label>
            <div class="col-md-1">
              <input class="ckeckonoff" name="header" value="{$data.header}" type="checkbox" />
            </div>
            <div class="col-md-4 marginTB">
              <p class="margin-none">{"تفعيل نموذج رأس الصفحة ليظهر بأعلى هذا النموذج"|gettext}</p> 
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"ذيل الصفحة"|gettext}</label>
            <div class="col-md-1">
              <input class="ckeckonoff" name="footer" value="{$data.footer}" type="checkbox" />
            </div>
            <div class="col-md-4 marginTB">
              <p class="margin-none">{"تفعيل نموذج ذيل الصفحة ليظهر بأسفل هذا النموذج"|gettext}</p> 
            </div>
          </div>
          {/if}
        </div>
      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
