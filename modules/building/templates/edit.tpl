<form action="" class="form-horizontal margin-none form-ajax" method="post" onsubmit="return verifyOwnerShares()">
  <input type="hidden" name="tempid" value="{$tempid}" />
  <input type="hidden" name="id" value="{$data.id}" />
  <input type="hidden" name="location[latitude]" id="geozone_latitude" value="{$data.location.latitude}" />
  <input type="hidden" name="location[longitude]" id="geozone_longitude" value="{$data.location.longitude}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <ul class="nav nav-tabs nav-justified" role="tablist" id="big-tabs">
        <li role="presentation" class="active"><a href="#basics" aria-controls="basics" role="tab" data-toggle="tab"><i class="fa fa-3x mb-10 fa-building"></i> {"بيانات الوحدة"|gettext}</a></li>
        <li role="presentation"><a href="#owner" aria-controls="owner" role="tab" data-toggle="tab"><i class="fa fa-3x mb-10 fa-certificate"></i> {"بيانات الملكية"|gettext}</a></li>
        <li role="presentation"><a href="#facilities" aria-controls="facilities" role="tab" data-toggle="tab"><i class="fa fa-3x mb-10 fa-tachometer"></i> {"المرافق العامة"|gettext}</a></li>
        <li role="presentation"><a href="#location" aria-controls="location" role="tab" data-toggle="tab"><i class="fa fa-3x mb-10 fa-map"></i> {"تفاصيل الموقع"|gettext}</a></li>
        <li role="presentation"><a href="#cfields" aria-controls="cfields" role="tab" data-toggle="tab"><i class="fa fa-3x mb-10 fa-cubes"></i> {"الحقول الإضافية"|gettext}</a></li>
        <li role="presentation"><a href="#attachments" aria-controls="attachments" role="tab" data-toggle="tab"><i class="fa fa-3x mb-10 fa-paperclip"></i> {"المرفقات"|gettext}</a></li>
      </ul>
      <!-- Tab panes -->
      <div class="tab-content">
        <div role="tabpanel" class="tab-pane active pt-40 pb-40" id="basics">
          <div class="row innerLR">
            <div class="col-md-6">
              <div class="form-group">
                <label class="col-md-3 control-label">{"العقار"|gettext} {"في حالة الرغبة بإدراج عقار داخله وحدات او فئات فرعية كمثال برج سكني داخله فئات كالشقق او مول داخله محلات"|gettext|help}</label>
                <div class="col-md-6">
                  <select name="locid" class="ajaxSelect" style="height: 34px;width:80%">
                    <option value="">{"اختر العقار"|gettext}</option>
                      {foreach $locations as $location}
                        <option value="{$location.id}" {if $data.locid eq $location.id || $smarty.get.locid eq $location.id}selected="selected"{/if}>{$location.location}</option>
                      {/foreach}
                  </select>
                  <a href="{$CPURL}/building/locations/add" class="btn btn-default pull-left" target="_blank"><i class="fa fa-plus-circle"></i></a>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="col-md-3 control-label">{"نسخ من"|gettext}</label>
                <div class="col-md-6">
                  <select name="copyfrom" class="form-control">
                  </select>
                </div>
              </div>
            </div>
          </div>
          <div class="row innerLR">
            <div class="col-md-6">
              <div class="form-group">
                <label class="col-md-3 control-label">{"اسم الوحدة او رقمها"|gettext} {"اسم العقار في حالة انه عقار رئيسي او اسم الفئة/الوحدة في حالة انها داخل عقار رئيسي"|gettext|help}</label>
                <div class="col-md-7">
                  <input class="form-control" name="title" value="{$data.title|clean}" type="text" required />
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="col-md-3 control-label">{"التاريخ المعتمد"|gettext}</label>
                <div class="col-md-3">
                  <select name="calendar" class="form-control calendarAutoChange" required>
                    <option value="1">{"ميلادي"|gettext}</option>
                    <option value="2" {if $data.calendar eq 2 OR (empty($data.calendar) AND $config.system_calendar eq 2)}selected="selected"{/if}>{"هجري"|gettext}</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <div class="row innerLR">
            <div class="col-md-6">
              <div class="form-group">
                <label class="col-md-3 control-label">{"نوع العقار"|gettext}</label>
                <div class="col-md-4">
                  <select name="type" class="form-control" required>
                    <option value="rent">{"تأجير"|gettext}</option>
                    <option value="sale" {if $data.typeid eq "sale"}selected="selected"{/if}>{"بيع"|gettext}</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"فئة العقار"|gettext}</label>
                <div class="col-md-7">
                  <select name="buildcat" class="ajaxSelect" required style="height: 34px;width:80%">
                    <option value="">{"اختر فئة"|gettext}</option>
                      {foreach $buildcats as $buildcat}
                        <option value="{$buildcat.id}" {if $data.buildcat eq $buildcat.id}selected="selected"{/if}>{$buildcat.title|clean}</option>
                      {/foreach}
                  </select>
                  <a href="{$CPURL}/buildcats/add" class="btn btn-default pull-left quick-form"><i class="fa fa-plus-circle"></i></a>
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"المدينة"|gettext}</label>
                <div class="col-md-7">
                  <select name="citytid" class="ajaxSelect" required style="height: 34px;width:80%">
                    <option value="">{"اختر المدينة"|gettext}</option>
                      {foreach $cities as $city}
                        <option value="{$city.id}" {if $data.citytid eq $city.id}selected="selected"{/if}>{$city.name}</option>
                      {/foreach}
                  </select>
                  <a href="{$CPURL}/city/add" class="btn btn-default pull-left quick-form"><i class="fa fa-plus-circle"></i></a>
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"رقم المخطط"|gettext}</label>
                <div class="col-md-6">
                  <input class="form-control" name="planno" value="{$data.planno|clean}" type="text" required />
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"اسم المنطقة"|gettext}</label>
                <div class="col-md-4">
                  <input class="form-control" name="zone" value="{$data.zone|clean}" type="text" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"المساحة"|gettext}</label>
                <div class="col-md-3 input-group">
                  <input class="form-control" name="size" value="{$data.size|clean}" type="number" />
                  <span class="input-group-addon">{"م2"|gettext}</span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"مفروشة"|gettext}</label>
                <div class="col-md-3">
                  <select name="furnished">
                    <option value="0">{"غير محدد"|gettext}</option>
                    <option value="1" {if $data.furnished eq 1}selected="selected"{/if}>{"نعم"|gettext}</option>
                    <option value="2" {if $data.furnished eq 2}selected="selected"{/if}>{"لا"|gettext}</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group buildForRent">
                <label class="col-md-3 control-label">{"الغرض"|gettext}</label>
                <div class="col-md-3 input-group pull-left">
                  <select name="tax" class="form-control">
                    <option value="habit">{"سكني"|gettext}</option>
                    <option value="comm" {if $data.tax eq "comm"}selected="selected"{/if}>{"تجاري"|gettext}</option>
                  </select>
                </div>
                <div class="col-md-4 input-group pull-left">
                  <select name="buyercat" class="form-control" required>
                    <option value="">{"فئة المستأجر"|gettext}</option>
                      {foreach $buycats as $buycat}
                        <option value="{$buycat.id}" {if $data.buyercat eq $buycat.id}selected="selected"{/if}>{$buycat.title|clean}</option>
                      {/foreach}
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"الحي"|gettext}</label>
                <div class="col-md-7">
                  <select name="district" class="ajaxSelect" required style="height: 34px;width:80%">
                    <option value="">{"اختر الحي"|gettext}</option>
                      {foreach $districts as $district}
                        <option value="{$district.id}" {if $data.districtid eq $district.id}selected="selected"{/if}>{$district.name}</option>
                      {/foreach}
                  </select>
                  <a href="{$CPURL}/district/add" class="btn btn-default pull-left quick-form"><i class="fa fa-plus-circle"></i></a>
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"رقم اللوحة"|gettext} {"لوحة الإعلان التي وضعت في الموقع"|gettext|help}</label>
                <div class="col-md-5">
                  <input class="form-control" name="platno" value="{$data.platno|clean}" type="text" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"رقم المبنى"|gettext} {"رقم المبنى الذي يضم العقار او رقم القطعه في حالة ارض مثلا"|gettext|help}</label>
                <div class="col-md-3">
                  <input class="form-control" name="buildno" value="{$data.buildno|clean}" type="text" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"اسم الشارع"|gettext}</label>
                <div class="col-md-4">
                  <input class="form-control" name="street" value="{$data.street|clean}" type="text" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"رقم الطابق"|gettext}</label>
                <div class="col-md-3">
                  <input class="form-control" name="floor" value="{$data.floor|clean}" type="number" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-md-3 control-label">{"عدد الغرف"|gettext}</label>
                <div class="col-md-3">
                  <input class="form-control" name="rooms" value="{$data.rooms|clean}" type="number" />
                </div>
              </div>
            </div>
          </div>
          <div class="row innerLR">
            <div class="col-md-6">
              <div class="form-group">
                <label class="col-md-3 control-label">{"وصف العقار"|gettext}</label>
                <div class="col-md-9">
                  <textarea class="form-control" name="details" rows="10" required>{$data.details|clean}</textarea>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="col-md-3 control-label">{"عنوان العقار"|gettext}</label>
                <div class="col-md-9">
                  <textarea class="form-control" name="loc_details" rows="10" required>{$data.loc_details|clean}</textarea>
                </div>
              </div>
            </div>
          </div>
          <div class="row innerLR">
            <div class="col-md-6">
              <div class="form-group buildForSale">
                <label class="col-md-3 control-label">{"قيمة حد البيع"|gettext}</label>
                <div class="col-md-4 input-group">
                  <input class="form-control" name="max" value="{$data.max|clean}" type="number" step="any" />
                  <span class="input-group-addon">{$config.currency}</span>
                </div>
              </div>
              <div class="form-group buildForRent">
                <label class="col-md-3 control-label shrink">{"قيمة عمولة المكتب"|gettext}</label>
                <div class="col-md-4 input-group">
                  <input class="form-control" name="rentcomm" value="{$data.rentcomm|clean}" type="number" step="any" required />
                  <span class="input-group-addon">{$config.currency}</span>
                </div>
              </div>
              <div class="form-group buildForRent">
                <label class="col-md-3 control-label">{"قيمة الإيجار السنوي"|gettext}</label>
                <div class="col-md-4 input-group">
                  <input class="form-control" name="rentvalue" value="{$data.rentvalue|clean}" type="number" step="any" required />
                  <span class="input-group-addon">{$config.currency}</span>
                </div>
              </div>
              <div class="form-group group-addon-select">
                <label class="col-md-3 control-label">{"تكلفة إدارة المِلك"|gettext} {"تكلفة إدارة المِلك تُخصم من مُلاك العقار وتضاف إلى ارباح المكتب"|gettext|help}</label>
                <div class="col-md-5 input-group pull-left">
                  <input class="form-control" name="mcost" value="{$data.mcost|default:'0'}" min="0" step="100" type="number" />
                  <span class="input-group-addon">
                  <input name="mcost_cycle_old" value="{$data.mcost_cycle|clean}" type="hidden" />
                  <select name="mcost_cycle" class="noSelect2">
                    <option value="">{"دورة التحصيل"|gettext}</option>
                    <option value="once" {if $data.mcost_cycle eq "once"}selected="selected"{/if}>{"تُحصل مرة واحدة"|gettext}</option>
                    <option value="month" {if $data.mcost_cycle eq "month"}selected="selected"{/if}>{"تُحصل شهرياً"|gettext}</option>
                    <option value="quartyear" {if $data.mcost_cycle eq "quartyear"}selected="selected"{/if}>{"تُحصل ربع سنوياً"|gettext}</option>
                    <option value="midyear" {if $data.mcost_cycle eq "midyear"}selected="selected"{/if}>{"تُحصل نصف سنوياً"|gettext}</option>
                    <option value="year" {if $data.mcost_cycle eq "year"}selected="selected"{/if}>{"تُحصل سنوياً"|gettext}</option>
                  </select>
                </span>
                </div>
                <div class="col-md-3 pull-left">
                  <input name="mcost_start_date_old" value="{$data.mcost_start_date|clean}" type="hidden" />
                  <input class="form-control datepicker" name="mcost_start_date" value="{$data.mcost_start_date|clean}" type="text" placeholder="{"تبدأ من تاريخ"|gettext}" style="height: 39px" />
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group buildForSale">
                <label class="col-md-3 control-label shrink">{"نسبة عمولة المكتب"|gettext}</label>
                <div class="col-md-3 input-group">
                  <input class="form-control" name="salecomm" value="{$data.salecomm|clean}" type="number" step="any" required />
                  <span class="input-group-addon"><i class="fa fa-percent"></i></span>
                </div>
              </div>
              <div class="form-group buildForRent">
                <label class="col-md-3 control-label">{"نظام العقد"|gettext}</label>
                <div class="col-md-9">
                  <select name="period" class="fit-content" required>
                    <option value="year" {if $data.period eq "year"}selected="selected"{/if}>{"سنوي"|gettext}</option>
                    <option value="month" {if $data.period eq "month"}selected="selected"{/if}>{"شهري"|gettext}</option>
                    <option value="day" {if $data.period eq "day"}selected="selected"{/if}>{"يومي"|gettext}</option>
                  </select>
                  <div>
                    <select name="payment" class="fit-content" required>
                      <option value="">{"طريقة دفع الإيجار"|gettext}</option>
                      <option value="month" {if $data.payment eq "month"}selected="selected"{/if}>{"دفع شهري"|gettext}</option>
                      <option value="quartyear" {if $data.payment eq "quartyear"}selected="selected"{/if}>{"دفع ربع سنوي"|gettext}</option>
                      <option value="midyear" {if $data.payment eq "midyear"}selected="selected"{/if}>{"دفع نصف سنوي"|gettext}</option>
                      <option value="year" {if $data.payment eq "year"}selected="selected"{/if}>{"دفع سنوي"|gettext}</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="form-group buildForRent">
                <label class="col-md-3 control-label">{"قيمة الإيجار الشهري"|gettext}</label>
                <div class="col-md-4 input-group">
                  <input class="form-control" name="rent_month" value="{$data.rent_month|clean}" type="number" step="any" required />
                  <span class="input-group-addon">{$config.currency}</span>
                </div>
              </div>
              <div class="form-group buildForRent">
                <label class="col-md-3 control-label">{"قيمة الإيجار اليومي"|gettext}</label>
                <div class="col-md-4 input-group">
                  <input class="form-control" name="rent_day" value="{$data.rent_day|clean}" type="number" step="any" required />
                  <span class="input-group-addon">{$config.currency}</span>
                </div>
              </div>
            </div>
          </div>
          <div class="row innerLR buildForSale">
            <h4 class="innerAll bg-container margin-none"><i class="fa fa-gavel"></i> {"سومات البيع"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default" onclick="addNewBid()"><i class="fa fa-plus-circle"></i></button></h4>
            <div class="row innerAll">
              <div class="col-md-12 bidsAjaxHolder">
                  {if empty($data.bids.amount)}
                    <div class="col-md-10 col-md-offset-1">
                      <div class="alert alert-info text-center">{"يمكنك اضافة عدد من سومات البيع بالضغط على"|gettext}<div class="btn-group btn-group-xs"><button type="button" onclick="addNewBid()" class="btn btn-default"><i class="fa fa-plus-circle"></i></button></div>{"بجانب العنوان"|gettext}</div>
                    </div>
                  {/if}
                  {section name=op loop=count($data.bids)+1}
                    <div class="col-md-12 {if $smarty.section.op.last}bidsAjaxTemplate hide2{else}bidsAjaxCloned{/if}">
                      <div class="form-group">
                        <div class="col-md-1">
                          <button type="button" class="btn btn-default" onclick="$(this).closest('.form-group').remove()"><i class="fa fa-minus-circle"></i></button>
                        </div>
                        <div class="col-md-5">
                          <input class="form-control" name="bids[name][]" value="{$data.bids.name[op]}" type="text" placeholder="{"اسم صاحب السوم"|gettext}" required />
                        </div>
                        <div class="col-md-3">
                          <input class="form-control" name="bids[mobile][]" value="{$data.bids.mobile[op]}" placeholder="{"رقم الجوال"|gettext}" type="number" />
                        </div>
                        <div class="col-md-3">
                          <input class="form-control" name="bids[amount][]" value="{$data.bids.amount[op]}" placeholder="{"قيمة السوم"|gettext}" type="number" min="0" step="any" required />
                        </div>
                      </div>
                    </div>
                  {/section}
              </div>
            </div>
          </div>
        </div>
        <div role="tabpanel" class="tab-pane pt-40 pb-40" id="owner">
          <div class="row innerLR">
            <h4 class="innerAll bg-container margin-none"><i class="fa fa-users"></i> {"مُلاك العقار"|gettext}</h4>
            <div class="row innerAll">
              <div class="col-md-12 ownerAjaxHolder">
                <div class="form-group hang-form">
                  <label class="col-md-2 col-md-offset-2 control-label">{"اسم المالك"|gettext}</label>
                  <div class="col-md-8">
                    <select class="ownerAjaxSearch noSelect2" style="height: 34px;width:60%"></select>
                    <a href="{$CPURL}/owner/add" class="btn btn-default quick-form"><i class="fa fa-plus-circle"></i> {"إضافة مالك جديد"|gettext}</a>
                  </div>
                </div>
                  {section name=op loop=count($data.owner.id)+1}
                    <div class="form-group {if $smarty.section.op.last}ownerAjaxTemplate hide2{else}ownerAjaxCloned{/if}">
                      <input name="owner[id][]" value="{$data.owner.id[op]}" type="hidden" />
                      <div class="col-md-1 col-md-offset-1">
                        <button type="button" class="btn btn-default" onclick="$(this).closest('.form-group').remove()"><i class="fa fa-minus-circle"></i></button>
                      </div>
                      <div class="col-md-5">
                        <input class="form-control" name="owner[name][]" value="{$data.owner.name[op]}" type="text" readonly />
                      </div>
                      <div class="col-md-2 input-group pull-left">
                        <input class="form-control" name="owner[share][]" value="{$data.owner.share[op]|default:'100'}" data-toggle="tooltip" data-placement="top" title="{"توزيع الأرباح"|gettext}" placeholder='{"توزيع الأرباح"|gettext}' type="number" min="1" max="100" step="any" required />
                        <span class="input-group-addon">%</span>
                      </div>
                      <div class="col-md-2 input-group pull-left">
                        <input class="form-control" name="owner[outgoings][]" value="{$data.owner.outgoings[op]|default:'100'}" data-toggle="tooltip" data-placement="top" title="{"توزيع المصاريف"|gettext}" placeholder='{"توزيع المصاريف"|gettext}' type="number" min="0" max="100" step="any" required />
                        <span class="input-group-addon">%</span>
                      </div>
                    </div>
                  {/section}
              </div>
            </div>
          </div>
          <div class="row innerLR">
            <h4 class="innerAll bg-container margin-none"><i class="fa fa-file-text"></i> {"وثائق التملك"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default" onclick="addNewDead()"><i class="fa fa-plus-circle"></i></button></div></h4>
            <div class="row innerAll">
              <div class="col-md-12 deedInfoHolder">
                  {if empty($data.plots.no)}
                    <div class="col-md-10 col-md-offset-1">
                      <div class="alert alert-info text-center"> {"يمكنك اضافة عدد من الوثائق بالضغط على"|gettext} <div class="btn-group btn-group-xs"><button type="button" onclick="addNewDead()" class="btn btn-default"><i class="fa fa-plus-circle"></i></button></div> {"بجانب العنوان"|gettext}</div>
                    </div>
                  {/if}
                  {section name=op loop=count($data.deed.no)+1}
                    <div class="col-md-12 {if $smarty.section.op.last}deadInfoTemplate hide2{else}deadInfoTable{/if}">
                      <div class="form-group">
                        <div class="col-md-3 input-group pull-left">
                          <select name="deed[typeid][]" class="form-control" required>
                              {foreach $deedtypes as $deedtype}
                                <option value="{$deedtype.id}" {if $data.deed.typeid[op] eq $deedtype.id}selected="selected"{/if}>{$deedtype.title|clean}</option>
                              {/foreach}
                          </select>
                          <span class="input-group-addon"><a href="{$CPURL}/deedtypes/add" class="quick-form"><i class="fa fa-plus-circle"></i></a></span>
                        </div>
                        <div class="col-md-2 input-group pull-left">
                          <input class="form-control" name="deed[no][]" value="{$data.deed.no[op]|clean}" type="text" placeholder="{"رقمها"|gettext}" />
                          <span class="input-group-addon"><i class="fa fa-fax"></i></span>
                        </div>
                        <div class="col-md-3 input-group pull-left">
                          <input class="form-control" name="deed[source][]" value="{$data.deed.source[op]|clean}" type="text" placeholder="{"مصدرها"|gettext}" />
                        </div>
                        <div class="col-md-3 input-group pull-left">
                          <input class="form-control datepicker" autocomplete="off" name="deed[date][]" value="{$data.deed.date[op]|clean}" type="text" placeholder="{"تاريخها"|gettext}" />
                          <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                        </div>
                        <div class="col-md-1">
                          <button type="button" class="btn btn-default" onclick="$(this).closest('div.deadInfoTable').remove()"><i class="fa fa-minus-circle"></i></button>
                        </div>
                      </div>
                    </div>
                  {/section}
              </div>
            </div>
          </div>
        </div>
        <div role="tabpanel" class="tab-pane pt-40 pb-40" id="facilities">
          <div class="row innerLR">
            <h4 class="innerAll bg-container margin-none"><i class="fa fa-tachometer"></i> {"عدادات المرافق"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default" onclick="addNewMeter()"><i class="fa fa-plus-circle"></i></button></div></h4>
            <div class="row innerAll">
              <div class="col-md-12 metersInfoHolder">
                  {if empty($data.meters.no)}
                    <div class="col-md-10 col-md-offset-1">
                      <div class="alert alert-info text-center"> {"يمكنك اضافة المزيد من العدادات بالضغط على"|gettext} <div class="btn-group btn-group-xs"><button type="button" onclick="addNewMeter()" class="btn btn-default"><i class="fa fa-plus-circle"></i></button></div>{"بجانب العنوان"|gettext}</div>
                    </div>
                  {/if}
                  {section name=op loop=count($data.meters.no)+1}
                    <div class="col-md-12 {if $smarty.section.op.last}metersInfoTemplate hide2{else}metersInfoTable{/if}">
                      <div class="form-group">
                        <div class="col-md-3 input-group pull-left">
                          <select name="meters[typeid][]" class="form-control" required>
                            <option value="">{"نوع العداد"|gettext}</option>
                              {foreach $meterstypes as $meterstype}
                                <option value="{$meterstype.id}" {if $data.meters.typeid[op] eq $meterstype.id}selected="selected"{/if}>{$meterstype.title|clean}</option>
                              {/foreach}
                          </select>
                          <span class="input-group-addon"><a href="{$CPURL}/meter_types/add" class="quick-form"><i class="fa fa-plus-circle"></i></a></span>
                        </div>
                        <div class="col-md-2 input-group pull-left">
                          <select name="meters[owner_type][]" class="form-control" required>
                            <option value="">{"نوع الإشتراك"|gettext}</option>
                            <option value="private" {if $data.meters.owner_type[op] eq 'private'}selected="selected"{/if}>{"خاص"|gettext}</option>
                            <option value="shared" {if $data.meters.owner_type[op] eq 'shared'}selected="selected"{/if}>{"مشترك"|gettext}</option>
                          </select>
                        </div>
                        <div class="col-md-2 pull-left">
                          <input class="form-control" name="meters[no][]" value="{$data.meters.no[op]|clean}" type="text" placeholder="{"رقم الإشتراك"|gettext}" required />
                        </div>
                        <div class="col-md-2 input-group pull-left">
                          <input class="form-control" name="meters[pay_code][]" value="{$data.meters.pay_code[op]|clean}" type="text" placeholder="{"رمز السداد"|gettext}" />
                        </div>
                        <div class="col-md-2 input-group pull-left">
                          <input class="form-control" name="meters[pay_no][]" value="{$data.meters.pay_no[op]|clean}" type="text" placeholder="{"رقم حساب السداد"|gettext}" />
                        </div>
                        <div class="col-md-1">
                          <button type="button" class="btn btn-default" onclick="$(this).closest('div.metersInfoTable').remove()"><i class="fa fa-minus-circle"></i></button>
                        </div>
                      </div>
                    </div>
                  {/section}
              </div>
            </div>
          </div>
        </div>
        <div role="tabpanel" class="tab-pane pt-40 pb-40" id="location">
          <div class="row innerLR">
            <h4 class="innerAll bg-container margin-none"><i class="fa fa-map"></i> {"الحدود والاطوال"|gettext} <div class="btn-group btn-group-xs"><button type="button" class="btn btn-default" onclick="addNewPlot()"><i class="fa fa-plus-circle"></i></button></div></h4>
            <div class="row innerAll">
              <div class="col-md-10 col-md-offset-1 plotInfoHolder">
                  {if empty($data.plots.no)}
                    <div class="alert alert-info text-center"> {"يمكنك اضافة عدد من القطع بالضغط على"|gettext} <div class="btn-group btn-group-xs"><button type="button" onclick="addNewPlot()" class="btn btn-default"><i class="fa fa-plus-circle"></i></button></div> {"بجانب المخطط"|gettext}</div>
                  {/if}
                  {section name=op loop=count($data.plots.no)+1}
                    <table style="border-bottom: 1px solid #e2e1e1" class="table table-condensed table-primary table-vertical-center {if $smarty.section.op.last}plotInfoTable hide2{/if}">
                      <tr>
                        <th class="center" rowspan="4" style="width: 20%;background: #e9f3fb">
                          <input class="form-control" name="plots[no][]" value="{$data.plots.no[op]}" type="number" placeholder="{"القطعة رقم"|gettext}" required /><br />
                          <div class="input-group">
                            <input class="form-control" name="plots[size][]" value="{$data.plots.size[op]}" type="number" placeholder="{"مساحتها"|gettext}" step="any" required />
                            <span class="input-group-addon">{"م2"|gettext}</span>
                          </div>
                        </th>
                        <td class="center">{"يحدها من الشمال"|gettext}</td>
                        <td class="center">
                          <select name="plots[north_type][]" class="form-control" onchange="plotChange(this)" required>
                              {foreach $borders as $border}
                                <option value="{$border.border}" data-attrs="{$border.attrs}" {if $data.plots.north_type[op] eq $border.border}selected="selected"{/if}>{$border.title}</option>
                              {/foreach}
                          </select>
                        </td>
                        <td class="center" style="width: 45%">
                          <div class="col-md-4 pull-left plotNo">
                            <input class="form-control" name="plots[north_plotno][]" value="{$data.plots.north_plotno[op]}" type="number" placeholder="{"رقمها"|gettext}" required />
                          </div>
                          <div class="col-md-4 input-group pull-left plotLength">
                            <input class="form-control" name="plots[north_length][]" value="{$data.plots.north_length[op]}" type="number" step="any" placeholder="{"طول"|gettext}" required />
                            <span class="input-group-addon">{"م"|gettext}</span>
                          </div>
                          <div class="col-md-4 input-group pull-left plotWidth">
                            <input class="form-control" name="plots[north_width][]" value="{$data.plots.north_width[op]}" type="number" step="any" placeholder="{"عرض"|gettext}" required />
                            <span class="input-group-addon">{"م"|gettext}</span>
                          </div>
                        </td>
                        <td class="center" rowspan="4"><button type="button" class="btn btn-default" onclick="$(this).closest('table').remove()"><i class="fa fa-minus-circle"></i></button></td>
                      </tr>
                      <tr>
                        <td class="center">{"يحدها من الغرب"|gettext}</td>
                        <td class="center">
                          <select name="plots[east_type][]" class="form-control" onchange="plotChange(this)" required>
                              {foreach $borders as $border}
                                <option value="{$border.border}" data-attrs="{$border.attrs}" {if $data.plots.east_type[op] eq $border.border}selected="selected"{/if}>{$border.title}</option>
                              {/foreach}
                          </select>
                        </td>
                        <td class="center" style="width: 45%">
                          <div class="col-md-4 pull-left plotNo">
                            <input class="form-control" name="plots[east_plotno][]" value="{$data.plots.east_plotno[op]}" type="number" placeholder="{"رقمها"|gettext}" required />
                          </div>
                          <div class="col-md-4 input-group pull-left plotLength">
                            <input class="form-control" name="plots[east_length][]" value="{$data.plots.east_length[op]}" type="number" step="any" placeholder="{"طول"|gettext}" required />
                            <span class="input-group-addon">{"م"|gettext}</span>
                          </div>
                          <div class="col-md-4 input-group pull-left plotWidth">
                            <input class="form-control" name="plots[east_width][]" value="{$data.plots.east_width[op]}" type="number" step="any" placeholder="{"عرض"|gettext}" required />
                            <span class="input-group-addon">{"م"|gettext}</span>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td class="center">{"يحدها من الجنوب"|gettext}</td>
                        <td class="center">
                          <select name="plots[south_type][]" class="form-control" onchange="plotChange(this)" required>
                              {foreach $borders as $border}
                                <option value="{$border.border}" data-attrs="{$border.attrs}" {if $data.plots.south_type[op] eq $border.border}selected="selected"{/if}>{$border.title}</option>
                              {/foreach}
                          </select>
                        </td>
                        <td class="center" style="width: 45%">
                          <div class="col-md-4 pull-left plotNo">
                            <input class="form-control" name="plots[south_plotno][]" value="{$data.plots.south_plotno[op]}" type="number" placeholder="{"رقمها"|gettext}" required />
                          </div>
                          <div class="col-md-4 input-group pull-left plotLength">
                            <input class="form-control" name="plots[south_length][]" value="{$data.plots.south_length[op]}" type="number" step="any" placeholder="{"طول"|gettext}" required />
                            <span class="input-group-addon">{"م"|gettext}</span>
                          </div>
                          <div class="col-md-4 input-group pull-left plotWidth">
                            <input class="form-control" name="plots[south_width][]" value="{$data.plots.south_width[op]}" type="number" step="any" placeholder="{"عرض"|gettext}" required />
                            <span class="input-group-addon">{"م"|gettext}</span>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td class="center">{"يحدها من الشرق"|gettext}</td>
                        <td class="center">
                          <select name="plots[west_type][]" class="form-control" onchange="plotChange(this)" required>
                              {foreach $borders as $border}
                                <option value="{$border.border}" data-attrs="{$border.attrs}" {if $data.plots.west_type[op] eq $border.border}selected="selected"{/if}>{$border.title}</option>
                              {/foreach}
                          </select>
                        </td>
                        <td class="center" style="width: 45%">
                          <div class="col-md-4 pull-left plotNo">
                            <input class="form-control" name="plots[west_plotno][]" value="{$data.plots.west_plotno[op]}" type="number" placeholder="{"رقمها"|gettext}" required />
                          </div>
                          <div class="col-md-4 input-group pull-left plotLength">
                            <input class="form-control" name="plots[west_length][]" value="{$data.plots.west_length[op]}" type="number" step="any" placeholder="{"طول"|gettext}" required />
                            <span class="input-group-addon">{"م"|gettext}</span>
                          </div>
                          <div class="col-md-4 input-group pull-left plotWidth">
                            <input class="form-control" name="plots[west_width][]" value="{$data.plots.west_width[op]}" type="number" step="any" placeholder="{"عرض"|gettext}" required />
                            <span class="input-group-addon">{"م"|gettext}</span>
                          </div>
                        </td>
                      </tr>
                    </table>
                  {/section}
              </div>
            </div>
          </div>
          <div class="row innerLR">
            <h4 class="innerAll bg-container margin-none"><i class="fa fa-location-arrow"></i> {"الموقع على الخريطة"|gettext}</h4>
            <div class="separator"></div>
            <div id="geozone_gmap_search">
              <input name="location[address]" value="{$data.location.address}" id="geozone_gmap_address" class="geozone_gmap_input" type="text" placeholder="{"ضع عنوان العقار للبحث عنه ثم حدد قطر الدائرة ثم اضفط على زر Enter"|gettext}" />
              <input name="location[radius]" value="{$data.location.radius}" id="geozone_gmap_radius" class="geozone_gmap_input" type="number" step="any" placeholder="{"قطر الدائرة بالمتر"|gettext}" style="width:150px" />
            </div>
            <div id="geozone-gmap"></div>
            <div class="separator"></div>
          </div>
        </div>
        <div role="tabpanel" class="tab-pane pt-40 pb-40" id="attachments">
          <div class="row innerLR">
            <h4 class="innerAll bg-container margin-none"><i class="fa fa-upload"></i> {"إرفاق صور أو مستندات تخص هذا العقار"|gettext}</h4>
            <div class="separator"></div>
            <ul class="file-uploaded-zone list-unstyled resume-documents">
                {foreach $data.files as $file}
                  <li><i class="trashbtn fa fa-trash-o pull-right delete-ajax" href="{$CPURL}/{$smarty.const.Module}/delattach?id={$file.id}" data-parent="li"></i><a href="{$smarty.const.MEDIAURL}/{$file.path}" target="_blank"><i class="fa fa-file-o"></i> {$file.name} <span>{$file.timepost|ardate:false:false}</span></a></li>
                {/foreach}
            </ul>
            <button type="button" href="{$CPURL}/home/attach?tempid={$tempid}" class="btn btn-default pull-right open-ajax" title={"إرفاق ملف جديد"|gettext}><i class="fa fa-cloud-upload"></i> {"إرفاق ملف جديد"|gettext}</button>
          </div>
          <div class="submit-block">
            <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
          </div>
        </div>
        <div role="tabpanel" class="tab-pane pt-40 pb-40" id="cfields"></div>
      </div>
      <div class="submit-block">
        <button type="button" id="btn-tabs-next" class="btn btn-primary"><i class="fa fa-arrow-left"></i> {"الخطوة التالية"|gettext}</button>
      </div>
    </div>
  </div>
</form>
