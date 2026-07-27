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
            <label class="col-md-2 control-label">{"اسم العقد"|gettext}</label>
            <div class="col-md-6">
              <input class="form-control" name="title" value="{$data.title|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"فئة العقد"|gettext}</label>
            <div class="col-md-4">
              <select name="cycle" class="form-control" required>
                  <option value="sale">{"عقد بيع"|gettext}</option>
                  <option value="day" {if $data.cycle eq "day"}selected="selected"{/if}>{"عقد إيجار يومي"|gettext}</option>
                  <option value="month" {if $data.cycle eq "month"}selected="selected"{/if}>{"عقد إيجار شهري"|gettext}</option>
                  <option value="year" {if $data.cycle eq "year"}selected="selected"{/if}>{"عقد إيجار سنوي"|gettext}</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
          <h4 class="innerAll bg-container margin-none"><i class="fa fa-gavel"></i> {"شروط العقد"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default" id="addNewRightsModule"><i class="fa fa-plus-circle"></i></button></div></h4>
          <div class="row innerAll">
            <div class="col-md-10 col-md-offset-1 extraRightsHolderModule">
              {if empty($data.content)}
              <div class="alert alert-info text-center">{"يمكنك اضافة عدد من الشروط  بالضفط على"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default"><i class="fa fa-plus-circle"></i></button></div> {"بالشريط العلوي"|gettext}</div>
              {/if}
              {section name=op loop=count($data.content)+1}
              <div class="form-group {if $smarty.section.op.last}extraRightsTemplateModule hide2{else}extraRightsClonedModule{/if}">
                <div class="col-md-11">
                  <input class="form-control" name="content[]" value="{$data.content[op]}" type="text" required />
                </div>
                <div class="col-md-1">
                  <button type="button" class="btn btn-default" onclick="$(this).closest('.form-group').remove()"><i class="fa fa-minus-circle"></i></button>
                </div>
              </div>
              {/section}
           </div>
        </div>
      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
