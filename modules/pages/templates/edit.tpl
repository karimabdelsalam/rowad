<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="id" value="{$smarty.get.id}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll inner-2x">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم الصفحة"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="title" value="{$data.title|clean}" type="text" required />
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"نوع الصفحة"|gettext}</label>
            <div class="col-md-8">
              <select class="form-control pageType" name="type" required>
                <option value="page">{"صفحة داخل النظام"|gettext}</option>
                <option value="linkout" {if $data.type eq "linkout"}selected="selected"{/if}>{"صفحة تحول الى رابط خارجي"|gettext}</option>
                <option value="linkin" {if $data.type eq "linkin"}selected="selected"{/if}>{"صفحة تحول الى رابط داخلي"|gettext}</option>
                <option value="menu" {if $data.type eq "menu"}selected="selected"{/if}>{"قائمة ينسدل منها صفحات"|gettext}</option>
              </select>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"مكان الظهور"|gettext}</label>
            <div class="col-md-8">
              <select class="form-control" name="positionid" required>
                {foreach from=$pagepositions item=position}
                  <option value="{$position.id}" {if $position.id eq $data.positionid}selected="selected"{/if}>{$position.position|clean}</option>
                {/foreach}
              </select>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"القائمة"|gettext}</label>
            <div class="col-md-8">
              <select class="form-control" name="parent" required>
                <option value="0">{"رئيسية لا تتبع لقوائم"|gettext}</option>
                {foreach from=$mainpages item=page}
                  <option value="{$page.id}" {if $page.id eq $data.parent}selected="selected"{/if}>{$page.title|clean}</option>
                {/foreach}
              </select>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"الحالة"|gettext}</label>
            <div class="col-md-8">
              <input class="ckeckonoff" name="active" value="{$data.active|clean}" type="checkbox" />
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group pageTypeLinkout {if $cuaction.name eq "add" OR $data.type neq "linkout"}hide2{/if}">
            <label class="col-md-2 control-label">{"رابط خارجي"|gettext}</label>
            <div class="col-md-6">
              <input type="url" class="form-control ltr" name="linkout" value="{$data.link|clean}" />
            </div>
          </div>
          <div class="form-group pageTypeLinkin {if $data.type eq "menu" OR $data.type eq "linkout"}hide2{/if}">
            <label class="col-md-2 control-label">{"رابط داخلي {"يمكنك وضع علامة # قبل الرابط ليتم التنقل الى الرابط بنفس الصفحة"|gettext}"|help}</label>
            <div class="col-md-6 input-group">
              <input type="text" class="form-control ltr" name="linkin" value="{$data.link|clean}" />
              <div class="input-group-addon ltr">{$CPURL}/</div>
            </div>
          </div>
          <div class="form-group pageTypeContent {if $cuaction.name neq "add" AND $data.type neq "page"}hide2{/if}">
            <label class="col-md-2 control-label">{"محتوى الصفحة"|gettext}</label>
            <div class="col-md-10">
              <textarea class="form-control ajaxeditor" name="content">{$data.content|clean}</textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="separator"></div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
        <button type="reset" class="btn btn-default"><i class="fa fa-times"></i>{"استرجاع التعديلات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
