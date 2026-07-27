<form action="{$CPURL}/{$cumodule.name}/{$cuaction.name}" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="tempid" value="{$tempid}" />
  <input type="hidden" name="id" value="{$data.id}" />
  <input type="hidden" name="gps_location[latitude]" id="geozone_latitude" value="{$data.gps_location.latitude}" />
  <input type="hidden" name="gps_location[longitude]" id="geozone_longitude" value="{$data.gps_location.longitude}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-9">
          <div class="form-group">
            <label class="col-md-2 control-label">{"مسمى العقار"|gettext}</label>
            <div class="col-md-7">
              <input class="form-control" name="location" value="{$data.location|clean}" type="text" required />
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <div class="col-md-6">
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
            <label class="col-md-3 control-label">{"عنوان العقار"|gettext}</label>
            <div class="col-md-9">
              <textarea class="form-control" name="loc_details" rows="10" required>{$data.loc_details|clean}</textarea>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="col-md-3 control-label">{"الحي"|gettext}</label>
            <div class="col-md-7">
              <select name="district" class="ajaxSelect" required style="height: 34px;width:80%">
                <option value="">{"اختر الحي"|gettext}</option>
                  {foreach $districts as $district}
                    <option value="{$district.id}" {if $data.district eq $district.id}selected="selected"{/if}>{$district.name}</option>
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
            <label class="col-md-3 control-label">{"رقم المبنى"|gettext}</label>
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
            <label class="col-md-3 control-label">{"عدد الطوابق"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control" name="floors" value="{$data.floors|clean}" type="number" />
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <h4 class="innerAll bg-container margin-none"><i class="fa fa-gps_location-arrow"></i> {"الموقع على الخريطة"|gettext}</h4>
        <div class="separator"></div>
        <div id="geozone_gmap_search">
          <input name="gps_location[address]" value="{$data.gps_location.address}" id="geozone_gmap_address" class="geozone_gmap_input" type="text" placeholder="{"ضع عنوان العقار للبحث عنه ثم حدد قطر الدائرة ثم اضفط على زر Enter"|gettext}" />
          <input name="gps_location[radius]" value="{$data.gps_location.radius}" id="geozone_gmap_radius" class="geozone_gmap_input" type="number" step="any" placeholder="{"قطر الدائرة بالمتر"|gettext}" style="width:150px" />
        </div>
        <div id="geozone-gmap"></div>
        <div class="separator"></div>
      </div>
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
  </div>
</form>
