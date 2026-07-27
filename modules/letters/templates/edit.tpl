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
            <label class="col-md-2 control-label">{"تاريخ الخطاب"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control datepicker" autocomplete="off" name="postdate" value="{$data.postdate|clean}" type="text" required />
            </div>
            <label class="col-md-2 col-md-offset-1 control-label">{"التاريخ المعتمد"|gettext}</label>
            <div class="col-md-3">
              <select name="calendar" class="form-control calendarAutoChange" required>
                <option value="1">{"ميلادي"|gettext}</option>
                <option value="2" {if $data.calendar eq 2 OR (empty($data.calendar) AND $config.debit_calendar eq 2)}selected="selected"{/if}>{"هجري"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"نوع الخطاب"|gettext}</label>
            <div class="col-md-3">
              <select name="type" class="form-control" required>
                {foreach $types as $type}
                <option value="{$type.name}" {if $data.type eq $type.name}selected="selected"{/if}>{$type.title|gettext}</option>
                {/foreach}
              </select>
            </div>
          </div>
          <div class="LetterType_official">
            <div class="form-group">
              <label class="col-md-2 control-label">{"موضوع الخطاب"|gettext}</label>
              <div class="col-md-6">
                <input class="form-control" name="official[subject]" value="{$data.official.subject|clean}" type="text" required />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-2 control-label">{"موجه إلي"|gettext}</label>
              <div class="col-md-4">
                <div class="btn-group" data-toggle="buttons">
                  <label class="btn btn-primary {if $data.official.sendto eq "person"}active{/if}"><input type="radio" name="official[sendto]" {if $data.official.sendto eq "person"}checked="checked"{/if} value="person" onchange="$('.officialpersonData').removeClass('hide2')">{"شخصي"|gettext}</label>
                  <label class="btn btn-primary {if $data.official.sendto eq "random"}active{/if}"><input type="radio" name="official[sendto]" {if $data.official.sendto eq "random"}checked="checked"{/if} value="random" onchange="$('.officialpersonData').addClass('hide2')">{"إلي من يهمه الأمر"|gettext}</label>
                </div>
              </div>
            </div>
            <div class="officialpersonData {if $data.official.sendto neq "person"}hide2{/if}">
              <div class="form-group">
                <label class="col-md-2 control-label">{"اسم الموجه إليه"|gettext}</label>
                <div class="col-md-4">
                  <input class="form-control" name="official[sendto_name]" value="{$data.official.sendto_name|clean}" type="text" required />
                </div>
                <div class="col-md-2">
                  <select name="official[sendto_title]" class="form-control" required>
                    <option {if $data.official.sendto_title eq "المحترم"}selected="selected"{/if}>{"المحترم"|gettext}</option>
                    <option {if $data.official.sendto_title eq "المحترمة"}selected="selected"{/if}>{"المحترمة"|gettext}</option>
                    <option {if $data.official.sendto_title eq "المحترمين"}selected="selected"{/if}>{"المحترمين"|gettext}</option>
                    <option {if $data.official.sendto_title eq "حفظه الله"}selected="selected"{/if}>{"حفظه الله"|gettext}</option>
                    <option {if $data.official.sendto_title eq "حفظها الله"}selected="selected"{/if}>{"حفظها الله"|gettext}</option>
                    <option {if $data.official.sendto_title eq "سلمه الله"}selected="selected"{/if}>{"سلمه الله"|gettext}</option>
                    <option {if $data.official.sendto_title eq "سلمها الله"}selected="selected"{/if}>{"سلمها الله"|gettext}</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-2 control-label">{"نص الخطاب"|gettext}</label>
              <div class="col-md-8">
                <textarea class="form-control" name="official[content]" rows="8" required>{$data.official.content|clean}</textarea>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-2 control-label">{"وصف الكاتب"|gettext}</label>
              <div class="col-md-6">
                <input class="form-control" name="official[sender_desc]" value="{$data.official.sender_desc|clean}" type="text" required />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-2 control-label">{"اسم الكاتب"|gettext}</label>
              <div class="col-md-4">
                <input class="form-control" name="official[sender_name]" value="{$data.official.sender_name|clean}" type="text" required />
              </div>
            </div>
          </div>
          
          <div class="LetterType_cancel">
            <div class="form-group">
              <label class="col-md-2 control-label">{"موجه إلي"|gettext}</label>
              <div class="col-md-4">
                <div class="btn-group" data-toggle="buttons">
                  <label class="btn btn-primary {if $data.cancel.sendto eq "person"}active{/if}"><input type="radio" name="cancel[sendto]" {if $data.cancel.sendto eq "person"}checked="checked"{/if} value="person" onchange="$('.cancelpersonData').removeClass('hide2')">{"شخصي"|gettext}</label>
                  <label class="btn btn-primary {if $data.cancel.sendto eq "random"}active{/if}"><input type="radio" name="cancel[sendto]" {if $data.cancel.sendto eq "random"}checked="checked"{/if} value="random" onchange="$('.cancelpersonData').addClass('hide2')">{"إلي من يهمه الأمر"|gettext}</label>
                </div>
              </div>
            </div>
            <div class="cancelpersonData {if $data.cancel.sendto neq "person"}hide2{/if}">
              <div class="form-group">
                <label class="col-md-2 control-label">{"وصف الموجه إليه"|gettext}</label>
                <div class="col-md-2">
                  <select name="cancel[sendto_desc]" class="form-control" required>
                    <option {if $data.cancel.sendto_desc eq "المكرم"}selected="selected"{/if}>{"المكرم"|gettext}</option>
                    <option {if $data.cancel.sendto_desc eq "المكرمة"}selected="selected"{/if}>{"المكرمة"|gettext}</option>
                    <option {if $data.cancel.sendto_desc eq "السادة"}selected="selected"{/if}>{"السادة"|gettext}</option>
                    <option {if $data.cancel.sendto_desc eq "معالي"}selected="selected"{/if}>{"معالي"|gettext}</option>
                    <option {if $data.cancel.sendto_desc eq "فضيلة"}selected="selected"{/if}>{"فضيلة"|gettext}</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-2 control-label">{"اسم الموجه إليه"|gettext}</label>
                <div class="col-md-4">
                  <input class="form-control" name="cancel[sendto_name]" value="{$data.cancel.sendto_name|clean}" type="text" required />
                </div>
                <div class="col-md-2">
                  <select name="cancel[sendto_title]" class="form-control" required>
                    <option {if $data.cancel.sendto_title eq "المحترم"}selected="selected"{/if}>{"المحترم"|gettext}</option>
                    <option {if $data.cancel.sendto_title eq "المحترمة"}selected="selected"{/if}>{"المحترمة"|gettext}</option>
                    <option {if $data.cancel.sendto_title eq "المحترمين"}selected="selected"{/if}>{"المحترمين"|gettext}</option>
                    <option {if $data.cancel.sendto_title eq "حفظه الله"}selected="selected"{/if}>{"حفظه الله"|gettext}ه</option>
                    <option {if $data.cancel.sendto_title eq "حفظها الله"}selected="selected"{/if}>{"حفظها الله"|gettext}</option>
                    <option {if $data.cancel.sendto_title eq "سلمه الله"}selected="selected"{/if}>{"سلمه الله"|gettext}</option>
                    <option {if $data.cancel.sendto_title eq "سلمها الله"}selected="selected"{/if}>{"سلمها الله"|gettext}</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          
          <div class="LetterType_rentraise">
            <div class="form-group">
              <label class="col-md-2 control-label">{"مبلغ الإيجار الجديد"|gettext}</label>
              <div class="col-md-3 input-group">
                <input class="form-control" name="rentraise[amount]" value="{$data.rentraise.amount|clean}" type="number" step="any" required />
                <span class="input-group-addon">{$config.currency}</span>
              </div>
            </div>
          </div>
          
          <div class="LetterType_review">
            <div class="form-group">
              <label class="col-md-2 control-label">{"اسم مقدم الطلب"|gettext}</label>
              <div class="col-md-4">
                <input class="form-control" name="review[provider]" value="{$data.review.provider|clean}" type="text" required />
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-2 control-label">{"صفته"|gettext}</label>
              <div class="col-md-4">
                <div class="btn-group" data-toggle="buttons">
                  <label class="btn btn-primary {if $data.review.provider_title eq "owner"}active{/if}"><input type="radio" name="review[provider_title]" {if $data.review.provider_title eq "owner"}checked="checked"{/if} value="owner" onchange="$('.reviewProviderData').addClass('hide2')">{"مالك"|gettext}</label>
                  <label class="btn btn-primary {if $data.review.provider_title eq "agent"}active{/if}"><input type="radio" name="review[provider_title]" {if $data.review.provider_title eq "agent"}checked="checked"{/if} value="agent" onchange="$('.reviewProviderData').removeClass('hide2')">{"وكيل شرعي"|gettext}</label>
                </div>
              </div>
            </div>
            <div class="form-group reviewProviderData {if $data.review.provider_title neq "agent"}hide2{/if}">
              <label class="col-md-2 control-label">{"اسم الموكل"|gettext}</label>
              <div class="col-md-5">
                <input class="form-control" name="review[agent_name]" value="{$data.review.agent_name|clean}" type="text" required />
              </div>
            </div>
          </div>
                
          <div class="LetterType_cancel LetterType_finish LetterType_rentraise">
            <div class="form-group">
              <label class="col-md-2 control-label">{"اسم المستأجر"|gettext}</label>
              <div class="col-md-4">
                <select class="buildrenterAjaxSearch noSelect2" name="renterid" style="width:100%" required>
                  {if !empty($data.renterid)}<option value="{$data.renterid}">{$data.renter.fullname}</option>{/if}
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-2 control-label">{"خاص بالعقار"|gettext}</label>
              <div class="col-md-5">
                <select class="form-control" name="buildid" required>
                  {if !empty($data.buildid)}
                    <option value="{$data.buildid}">{$data.build}</option>
                  {/if}
                </select>
              </div>
            </div>
          </div>
          
        </div>
        
      </div>
      
      <div class="row innerLR LetterType_review">
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"المدينة"|gettext}</label>
            <div class="col-md-6">
              <select name="review[city]" class="ajaxSelect" required style="height: 34px;width:80%">
                  <option value="">{"اختر المدينة"|gettext}</option>
                {foreach $cities as $city}
                  <option value="{$city.id}" {if $data.review.city eq $city.id}selected="selected"{/if}>{$city.name}</option>
                {/foreach}
              </select>
              <a href="{$CPURL}/city/add" class="btn btn-default pull-left quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"رقم القطعة"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="review[plotno]" value="{$data.review.plotno|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"قيمة التقييم"|gettext}</label>
            <div class="col-md-6 input-group">
              <input class="form-control" name="review[amount]" value="{$data.review.amount|clean}" type="number" step="any" required />
              <span class="input-group-addon">{$config.currency}</span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-4 control-label">{"الحي"|gettext}</label>
            <div class="col-md-6">
              <select name="review[district]" class="ajaxSelect" required style="height: 34px;width:80%">
                  <option value="">{"اختر الحي"|gettext}</option>
                {foreach $districts as $district}
                  <option value="{$district.id}" {if $data.review.district eq $district.id}selected="selected"{/if}>{$district.name}</option>
                {/foreach}
              </select>
              <a href="{$CPURL}/district/add" class="btn btn-default pull-left quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"المخطط"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="review[planno]" value="{$data.review.planno|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-4 control-label">{"اجمالى مساحة العقار"|gettext}</label>
            <div class="col-md-4 input-group">
              <input class="form-control" name="review[size]" value="{$data.review.size|clean}" type="text" required />
              <span class="input-group-addon">{"م2"|gettext}</span>
            </div>
          </div>
        </div>
        <div class="col-md-12">
          <div class="form-group no-border-space">
            <label class="col-md-2 control-label">{"وصف العقار"|gettext}</label>
            <div class="col-md-7">
              <textarea class="form-control" name="review[about]" rows="10" required>{$data.review.about|clean}</textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR LetterType_review">
        <h4 class="innerAll bg-container margin-none"><i class="fa fa-map"></i>{"الحدود والأطوال"|gettext} </h4>
        <div class="col-md-12">
          <table class="table table-condensed table-primary table-vertical-center">
              <tr>
                <td class="center no-border">{"يحدها من الشمال"|gettext}</td>
                <td class="center no-border">
                  <select name="review[plot][north_type]" class="form-control" onchange="plotChange(this)" required>
                    {foreach $borders as $border}
                      <option value="{$border.border}" data-attrs="{$border.attrs}" {if $data.review.plot.north_type eq $border.border}selected="selected"{/if}>{$border.title}</option>
                    {/foreach}
                  </select>
                </td>
                <td class="center no-border" style="width: 45%">
                  <div class="col-md-4 pull-left plotNo">
                    <input class="form-control" name="review[plot][north_plotno]" value="{$data.review.plot.north_plotno}" type="number" placeholder="{"رقمها"|gettext}" required />
                  </div>
                  <div class="col-md-4 input-group pull-left plotLength">
                    <input class="form-control" name="review[plot][north_length]" value="{$data.review.plot.north_length}" type="number" step="any" placeholder="{"طول"|gettext}" required />
                    <span class="input-group-addon">{"م"|gettext}</span>
                  </div>
                  <div class="col-md-4 input-group pull-left plotWidth">
                    <input class="form-control" name="review[plot][north_width]" value="{$data.review.plot.north_width}" type="number" step="any" placeholder="{"عرض"|gettext}" required />
                    <span class="input-group-addon">{"م"|gettext}</span>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="center">{"يحدها من الغرب"|gettext}</td>
                <td class="center">
                  <select name="review[plot][east_type]" class="form-control" onchange="plotChange(this)" required>
                    {foreach $borders as $border}
                      <option value="{$border.border}" data-attrs="{$border.attrs}" {if $data.review.plot.east_type eq $border.border}selected="selected"{/if}>{$border.title}</option>
                    {/foreach}
                  </select>
                </td>
                <td class="center" style="width: 45%">
                  <div class="col-md-4 pull-left plotNo">
                    <input class="form-control" name="review[plot][east_plotno]" value="{$data.review.plot.east_plotno}" type="number" placeholder="{"رقمها"|gettext}" required />
                  </div>
                  <div class="col-md-4 input-group pull-left plotLength">
                    <input class="form-control" name="review[plot][east_length]" value="{$data.review.plot.east_length}" type="number" step="any" placeholder="{"طول"|gettext}" required />
                    <span class="input-group-addon">{"م"|gettext}</span>
                  </div>
                  <div class="col-md-4 input-group pull-left plotWidth">
                    <input class="form-control" name="review[plot][east_width]" value="{$data.review.plot.east_width}" type="number" step="any" placeholder="{"عرض"|gettext}" required />
                    <span class="input-group-addon">{"م"|gettext}</span>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="center">{"يحدها من الجنوب"|gettext}</td>
                <td class="center">
                  <select name="review[plot][south_type]" class="form-control" onchange="plotChange(this)" required>
                    {foreach $borders as $border}
                      <option value="{$border.border}" data-attrs="{$border.attrs}" {if $data.review.plot.south_type eq $border.border}selected="selected"{/if}>{$border.title}</option>
                    {/foreach}
                  </select>
                </td>
                <td class="center" style="width: 45%">
                  <div class="col-md-4 pull-left plotNo">
                    <input class="form-control" name="review[plot][south_plotno]" value="{$data.review.plot.south_plotno}" type="number" placeholder="{"رقمها"|gettext}" required />
                  </div>
                  <div class="col-md-4 input-group pull-left plotLength">
                    <input class="form-control" name="review[plot][south_length]" value="{$data.review.plot.south_length}" type="number" step="any" placeholder="{"طول"|gettext}" required />
                    <span class="input-group-addon">{"م"|gettext}</span>
                  </div>
                  <div class="col-md-4 input-group pull-left plotWidth">
                    <input class="form-control" name="review[plot][south_width]" value="{$data.review.plot.south_width}" type="number" step="any" placeholder="{"عرض"|gettext}" required />
                    <span class="input-group-addon">{"م"|gettext}</span>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="center">{"يحدها من الشرق"|gettext}</td>
                <td class="center">
                  <select name="review[plot][west_type]" class="form-control" onchange="plotChange(this)" required>
                    {foreach $borders as $border}
                      <option value="{$border.border}" data-attrs="{$border.attrs}" {if $data.review.plot.west_type eq $border.border}selected="selected"{/if}>{$border.title}</option>
                    {/foreach}
                  </select>
                </td>
                <td class="center" style="width: 45%">
                  <div class="col-md-4 pull-left plotNo">
                    <input class="form-control" name="review[plot][west_plotno]" value="{$data.review.plot.west_plotno}" type="number" placeholder="{"رقمها"|gettext}" required />
                  </div>
                  <div class="col-md-4 input-group pull-left plotLength">
                    <input class="form-control" name="review[plot][west_length]" value="{$data.review.plot.west_length}" type="number" step="any" placeholder="{"طول"|gettext}" required />
                    <span class="input-group-addon">{"م"|gettext}</span>
                  </div>
                  <div class="col-md-4 input-group pull-left plotWidth">
                    <input class="form-control" name="review[plot][west_width]" value="{$data.review.plot.west_width}" type="number" step="any" placeholder="{"عرض"|gettext}" required />
                    <span class="input-group-addon">{"م"|gettext}</span>
                  </div>
                </td>
              </tr>
          </table>
        </div>
      </div>
      
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
