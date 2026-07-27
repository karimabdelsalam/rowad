<form action="" class="form-horizontal margin-none form-ajax" method="post">
    <div class="widget">
        <div class="widget-head">
            <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title}</h4>
        </div>
        <div class="widget-body innerAll inner-2x">
            <div class="row innerLR">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"الاسم"|gettext}</label>
                        <div class="col-md-8"><span class="form-control-static">{$message.name}</span></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"عنوان الرسالة"|gettext}</label>
                        <div class="col-md-8"><span class="form-control-static">{$message.subject}</span></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"البريد الإلكتروني"|gettext}</label>
                        <div class="col-md-8"><span class="form-control-static">{$message.email}</span></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{"رقم الهاتف"|gettext}</label>
                        <div class="col-md-8"><span class="form-control-static">{$message.mobile}</span></div>
                    </div>
                </div>
            </div>
            <div class="bg-gray innerAll inner-2x">
                <div class="row">
                    <div class="col-md-12">
                        <h4>{"الرسالة"|gettext}</h4>
                        <div class="row">
                            <div class="col-md-12 "><p>{$message.content|nl2br}</p></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="separator"></div>
            <div class="form-actions">
                <button href="{$CPURL}/{$cumodule.name}/reply/?id={$message.id}" title="{"إرسال رد بريدي"|gettext}" class="btn btn-primary open-ajax"><i class="fa fa-mail-reply"></i> {"إرسال رد"|gettext}</button>
                <button type="button" class="btn btn-default go-back"><i class="fa fa-arrow-circle-o-right"></i> {"الرجوع إلي قائمة الرسائل"|gettext}</button>
            </div>
        </div>
    </div>
</form>
