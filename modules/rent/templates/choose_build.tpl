<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerAll inner-2x">
        <div class="col-md-offset-2 col-md-4">
          <div class="widget">
            <div class="display-block innerAll text-center"><i class="fa fa-search" style="font-size:9em"></i></div>
            <div class="text-center innerAll">
              <span class="strong">{"ابحث في العقارات المتاحة للإيجار"|gettext}</span>
            </div>
          </div>
          <div class="text-center" style="height:55px">
            <select class="buildAjaxSearch noSelect2" style="width:100%"></select>
          </div>
        </div>
        <div class="col-md-offset-1 col-md-4">
          <div class="widget">
            <a href="{$CPURL}/building/add" class="display-block innerAll text-center"><i class="fa fa-plus-circle" style="font-size:185px"></i></a>
            <div class="text-center innerAll">
              <a href="{$CPURL}/building/add" class="strong">{"اضف عقار جديد"|gettext}</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
