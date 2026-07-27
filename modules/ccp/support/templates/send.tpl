<form action="{$CPURL}/{$cumodule.name}/{$cuaction.name}" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$data.id}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"نوع الطلب"|gettext}</label>
            <div class="col-md-2">
              <select class="form-control" name="type" required>
                <option value="request">{"request"|coregate:"supportTypes"}</option>
                <option value="enquiry">{"enquiry"|coregate:"supportTypes"}</option>
                <option value="problem">{"problem"|coregate:"supportTypes"}</option>
                <option value="suggestion">{"suggestion"|coregate:"supportTypes"}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"مرتبط بالعقار"|gettext}</label>
            <div class="col-md-3">
              <select class="form-control" name="buildid" required>
                {foreach $builds as $build}
                  <option value="{$build.id}">{$build.title} {if !empty($build.location)} [{$build.location}]{/if}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"عنوان الطلب"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="subject" value="{$data.subject|clean}" type="text" minlength="10" maxlength="100" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"التفاصيل"|gettext}</label>
            <div class="col-md-6">
              <textarea class="form-control" name="message" rows="12" minlength="10" maxlength="1000" required>{$data.message|clean}</textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"ارسال الطلب"|gettext}</button>
      </div>
    </div>
  </div>
</form>
