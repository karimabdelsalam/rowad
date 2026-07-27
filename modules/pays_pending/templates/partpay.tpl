{if empty($data)}
  <div class="widget">
    <div class="widget-head">
      <h4 class="heading"><i class="fa fa-{$cuaction.icon}"></i> {$cuaction.title|gettext|clean}</h4>
    </div>
    <div class="widget-body innerAll">
      <div class="row innerLR">
        <div class="col-md-12">
          <div class="alert alert-success" role="alert">{"لا يوجد اي دفعات مستحقة الدفع"|gettext}</div>
        </div>
      </div>
    </div>
  </div>
{else}
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
            <label class="col-md-2 control-label">{"تاريخ الإستحقاق"|gettext}</label>
            <div class="col-md-4">
              <span class="label label-default">{$data.paydate|ardate:false:false}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"قيمة"|gettext}</label>
            <div class="col-md-4">
              <span class="label label-success">{$data.paymenttype|gettext}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"إجمالى المبلغ"|gettext}</label>
            <div class="col-md-4">
              <span class="label label-primary">{$data.amount} {$config.currency}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"مدفوع حتى الآن"|gettext}</label>
            <div class="col-md-4">
              <span class="label label-warning">{$data.paidamount} {$config.currency}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"متبقي"|gettext}</label>
            <div class="col-md-4">
              <span class="label label-danger">{$data.amount-$data.paidamount} {$config.currency}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-2 control-label">{"دفعة جزئية"|gettext}</label>
            <div class="col-md-3 input-group">
              <input class="form-control" name="partamount" type="number" />
              <span class="input-group-addon">{$config.currency}</span>
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
{/if}
