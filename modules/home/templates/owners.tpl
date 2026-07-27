<form action="" class="form-horizontal margin-none form-ajax" method="post" onsubmit="verifyOwnerShares()">
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default" id="ownerAjaxAdder"><i class="fa fa-plus-circle"></i> {"إضافة مالك آخر للمنشأة"|gettext}</button></div></h4>
    </div>
    <div class="widget-body innerAll">
      <div class="ownerAjaxHolder">
        {section name=op loop=count($data)+1}
        <div class="row innerLR {if $smarty.section.op.last}ownerAjaxTemplate hide2{else}ownerAjaxCloned{/if}">
          <h4 class="innerAll bg-container margin-none">{"رقم المالك"|gettext} <span class="ownerSort badge badge-white">{$smarty.section.op.iteration}</span>
            <div class="btn-group btn-group-xs pull-right"><button type="button" class="btn btn-danger" onclick="$(this).closest('.ownerAjaxCloned').remove();sortOwners();"><i class="fa fa-minus-circle"></i> {"حذف المالك"|gettext}</button></div>
          </h4>
          <div class="separator"></div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="col-md-3 control-label">{"الاسم الأول"|gettext}</label>
              <div class="col-md-8">
                <input class="form-control" name="fname[]" value="{$data[op].fname|clean}" type="text" required tabindex="1" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"اسم الجد"|gettext}</label>
              <div class="col-md-8">
                <input class="form-control" name="lname[]" value="{$data[op].lname|clean}" type="text" required tabindex="3" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"نسبة التملك"|gettext}</label>
              <div class="col-md-3 input-group">
                <input class="form-control" name="share[]" value="{$data[op].share|clean|default:'100'}" type="number" step="any" required />
                <span class="input-group-addon"><i class="fa fa-percent"></i></span>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"الجنسية"|gettext}</label>
              <div class="col-md-5">
                <select name="nationality[]" class="{if $smarty.section.op.last}ajaxSelectFreezed{else}ajaxSelect{/if}" required style="width:100%;height: 34px;">
                  {foreach $nationalities as $nationality}
                    <option value="{$nationality.id}" {if $data[op].nationality eq $nationality.id}selected="selected"{/if}>{$nationality.name}</option>
                  {/foreach}
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"نوع الهوية"|gettext}</label>
              <div class="col-md-5">
                <select name="idtype[]" class="form-control" required>
                  <option value="idcard" {if $data[op].idtype eq "idcard"}selected="selected"{/if}>{"هوية وطنية"|gettext}</option>
                  <option value="passport" {if $data[op].idtype eq "passport"}selected="selected"{/if}>{"جواز سفر"|gettext}</option>
                  <option value="visa" {if $data[op].idtype eq "visa"}selected="selected"{/if}>{"إقامة"|gettext}</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"صورة من الهوية"|gettext}</label>
              <div class="col-md-8 input-group">
                <input class="form-control" name="idcopy[]" type="file" {if empty($data[op].idcopy)}required{/if} />
                {if !empty($data[op].idcopy)}
                  <input name="idcopy[]" type="hidden" value="{$data[op].idcopy}" />
                  <span class="input-group-addon"><a href="{$smarty.const.MEDIAURL}/{$data[op].idcopy|clean}" data-gallery="zoom"><i class="fa fa-search-plus"></i></a></span>
                {/if}
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"جهة العمل"|gettext}</label>
              <div class="col-md-8">
                <input class="form-control" name="work[]" value="{$data[op].work|clean}" type="text" required />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"رقم هاتف المنزل"|gettext}</label>
              <div class="col-md-6 input-group">
                <input class="form-control" name="homephone[]" value="{$data[op].homephone|clean}" type="number" minlength="9" maxlength="9" placeholder="01xxxxxxx" />
                <span class="input-group-addon"><i class="fa fa-phone"></i></span>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"صندوق البريد"|gettext}</label>
              <div class="col-md-4 input-group">
                <input class="form-control" name="zipcode[]" value="{$data[op].zipcode|clean}" type="text" />
                <span class="input-group-addon"><i class="fa fa-archive"></i></span>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="col-md-3 control-label">{"اسم الأب"|gettext}</label>
              <div class="col-md-8">
                <input class="form-control" name="fathname[]" value="{$data[op].fathname|clean}" type="text" required tabindex="2" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"اسم العائلة"|gettext}</label>
              <div class="col-md-8">
                <input class="form-control" name="famname[]" value="{$data[op].famname|clean}" type="text" required tabindex="4" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"المدينة"|gettext}</label>
              <div class="col-md-4">
                <select name="city[]" class="{if $smarty.section.op.last}ajaxSelectFreezed{else}ajaxSelect{/if}" required style="width:100%;height: 34px;">
                  {foreach $cities as $city}
                    <option value="{$city.id}" {if $data[op].city eq $city.id}selected="selected"{/if}>{$city.name}</option>
                  {/foreach}
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"عنوان السكن"|gettext}</label>
              <div class="col-md-9">
                <input class="form-control" name="address[]" value="{$data[op].address|clean}" type="text" maxlength="150" required />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"مصدرها"|gettext}</label>
              <div class="col-md-6">
                <input class="form-control" name="idsource[]" value="{$data[op].idsource|clean}" type="text" required />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"رقم الجوال"|gettext}</label>
              <div class="col-md-6 input-group">
                <input class="form-control" name="mobile[]" value="{$data[op].mobile|clean}" type="number" minlength="{$config.mobile_length}" maxlength="14" placeholder="{$config.phone_code}xxxxxxxxx" required />
                <span class="input-group-addon"><i class="fa fa-mobile"></i></span>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"رقم هاتف العمل"|gettext}</label>
              <div class="col-md-6 input-group">
                <input class="form-control" name="workphone[]" value="{$data[op].workphone|clean}" type="number" minlength="{$config.phone_length}" maxlength="13" placeholder="{$config.phone_code}xxxxxxxxx" />
                <span class="input-group-addon"><i class="fa fa-phone"></i></span>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"بريد إلكتروني"|gettext}</label>
              <div class="col-md-6 input-group">
                <input class="form-control" name="email[]" value="{$data[op].email|clean}" type="email" />
                <span class="input-group-addon"><i class="fa fa-inbox"></i></span>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">{"الرمز البريدي"|gettext}</label>
              <div class="col-md-4 input-group">
                <input class="form-control" name="postal[]" value="{$data[op].postal|clean}" type="text" />
                <span class="input-group-addon"><i class="fa fa-archive"></i></span>
              </div>
            </div>
          </div>
        </div>
        {/section}
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ التغييرات"|gettext}</button>
    </div>
  </div>
</form>
