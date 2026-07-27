<form action="" class="form-horizontal margin-none form-ajax" method="post">
    <div class="widget">
        <div class="widget-head">
            <h4 class="heading"><i class="fa fa-{$cuaction.icon|clean}"></i> {$cuaction.title|gettext|clean}</h4>
        </div>
        <div class="widget-body innerAll">
            <div class="row innerLR">
                <div class="col-md-12">
                    <div class="form-group">
                        <label class="col-md-2 control-label">{"ملف قائمة الاتصال"|gettext}</label>
                        <div class="col-md-5">
                            <input class="form-control" name="contacts" type="file" required />
                            <span class="help-inline">{"الامتدادات المسموح بها"|gettext} (CSV, VCF, TXT)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="submit-block">
                <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"استيراد قائمة الاتصال"|gettext}</button>
            </div>
        </div>
    </div>
</form>
