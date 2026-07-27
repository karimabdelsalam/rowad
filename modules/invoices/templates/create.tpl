<form action="" class="form-horizontal margin-none form-ajax" method="post">
  <input type="hidden" name="query" value="{$data.query}" />
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"تاريخ الفاتورة"|gettext}</label>
            <div class="col-md-3">
              <input class="form-control datepicker" autocomplete="off" name="created_date" value="{$data.created_date|clean}" type="text" required />
            </div>
            <label class="col-md-2 col-md-offset-1 control-label">{"التاريخ المعتمد"|gettext}</label>
            <div class="col-md-3">
              <select name="calendar" class="form-control calendarAutoChange" required>
                <option value="1">{"ميلادي"|gettext}</option>
                <option value="2" {if $data.calendar eq 2 OR (empty($data.calendar) AND $config.invoices_calendar eq 2)}selected="selected"{/if}>{"هجري"|gettext}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"موجه إلي"|gettext}</label>
            <div class="col-md-4">
              <div class="btn-group" data-toggle="buttons">
                <label class="btn btn-primary active"><input type="radio" name="customer_type" checked="checked" value="person" onchange="$('.nonExistingCustomer').addClass('hide2');$('.existingCustomer').removeClass('hide2');">{"عميل مسجل"|gettext}</label>
                <label class="btn btn-primary"><input type="radio" name="customer_type" value="random" onchange="$('.existingCustomer').addClass('hide2');$('.nonExistingCustomer').removeClass('hide2');">{"عميل غير مسجل"|gettext}</label>
              </div>
            </div>
          </div>
          <div class="form-group existingCustomer">
            <label class="col-md-2">{"اسم العميل"|gettext}</label>
            <div class="col-md-6">
              <select class="buyerSearch noSelect2" name="buyerid" style="width:100%">
                {if $smarty.get.buyerid}<option value="{$smarty.get.buyerid}" selected>{$buyer.fullname}</option>{/if}
                {if $data.paidto.id}<option value="{$data.paidto.id}" selected>{$data.paidto.fullname}</option>{/if}
              </select>
            </div>
          </div>
          <div class="form-group hide2 nonExistingCustomer">
            <label class="col-md-2 control-label">{"تُحرر إلى"|gettext}</label>
            <div class="col-md-5 input-group">
              <input class="form-control" name="name" value="{$data.paidto.fullname|clean}" type="text" required />
              <span class="input-group-addon"><i class="fa fa-user"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"مطلوب دفع"|gettext}</label>
            <div class="col-md-3">
              <span class="label label-primary">{$data.amount_to_pay} {$config.currency}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"المبلغ المدفوع"|gettext}</label>
            <div class="col-md-2 input-group">
              <input class="form-control" name="paid" value="{$data.paid|clean}" type="number" step="any" required />
              <span class="input-group-addon">{$config.currency}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="form-group">
            <label class="col-md-2 control-label">{"ملاحظات"|gettext}</label>
            <div class="col-md-8">
              <textarea class="form-control" name="notes" rows="8">{$data.notes|clean}</textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="submit-block">
        <button type="submit" class="btn btn-primary"><i class="fa fa-check-circle"></i> {"حفظ البيانات"|gettext}</button>
      </div>
    </div>
  </div>
</form>
