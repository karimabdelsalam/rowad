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
            <label class="col-md-2 control-label">{"اسم صاحب الحساب"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="fullname" value="{$data.fullname|clean}" type="text" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"اسم البنك"|gettext}</label>
            <div class="col-md-3">
              <select class="ajaxSelect" style="width: 80%" name="bankid" required>
                {foreach $banks as $bank}
                <option value="{$bank.id}" {if $data.bankid eq $bank.id}selected="selected"{/if}>{$bank.title|clean}</option>
                {/foreach}
              </select>
              <a href="{$CPURL}/banks/add" class="btn btn-default inline-block quick-form"><i class="fa fa-plus-circle"></i></a>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"رقم الفرع"|gettext}</label>
            <div class="col-md-2">
              <input class="form-control" name="branchno" value="{$data.branchno|clean}" type="number" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"رقم الحساب"|gettext}</label>
            <div class="col-md-4">
              <input class="form-control" name="accno" value="{$data.accno|clean}" type="text" required />
            </div>
            {if !empty($data.id)}
            <div class="col-md-4">
              <a href="{$CPURL}/bank_accounts/printable/?id={$data.id}" target="_blank" class="btn btn-success"><i class="fa fa-print"></i> {"طباعة رقم الحساب"|gettext}</a>
            </div>
            {/if}
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"رقم الآيبان"|gettext}</label>
            <div class="col-md-5">
              <input class="form-control" name="iban" value="{$data.iban|clean}" type="text" minlength="24" maxlength="24" required />
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"رصيد الحساب البنكي الآن"|gettext}</label>
            <div class="col-md-3 input-group">
              <input class="form-control" name="balance" value="{$data.balance|clean}" type="number" step="any" required />
              <span class="input-group-addon">{$config.currency}</span>
            </div>
          </div>
          {if !empty($data.id)}
          <div class="form-group">
            <label class="col-md-2 control-label">{"إرسال رقم الحساب SMS"|gettext}</label>
            <div class="col-md-3 input-group pull-left">
              <input class="form-control" id="smsNumber" type="number" minlength="{$config.mobile_length}" maxlength="14" placeholder="{$config.phone_code}xxxxxxxxx" />
              <span class="input-group-addon"><i class="fa fa-mobile"></i></span>
            </div>
            <div class="col-md-3">
              <button href="{$CPURL}/bank_accounts/sms/?id={$data.id}" data-load="{"جارى الإرسال"|gettext}" data-success="{"تم بنجاح"|gettext}" class="btn btn-success sendSMS"><i class="fa fa-mobile-phone"></i> {"ارسال الآن"|gettext}</button>
            </div>
          </div>
          {/if}
        </div>
      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
