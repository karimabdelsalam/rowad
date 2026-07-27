<script type="text/javascript">
  $(document).ready(function () {

    $(".buildAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن العقار بالإسم او رقم العقار') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/building/search/?type=sell&free=1",
        dataType: 'json',
        results: function (data) {
          return {results: data};
        },
        data: function (params) {
          return {
            q: params.term,
            callpage: params.page || 1
          }
        }
      }
    });
    $(".buildAjaxSearch").change(function () {
      window.location = "<?php echo CPURL;?>/sell/add/?id=" + $(this).val();
    });

    $("#partsAjaxAdder").click(function () {
      $(".partsAjaxHolder div.alert").remove();
      $(".partsAjaxHolder").append($(".partsAjaxTemplate").clone());
      $(".partsAjaxHolder .partsAjaxTemplate:last").addClass("partsAjaxCloned").removeClass("partsAjaxTemplate");
      $(".partsAjaxHolder .partsAjaxCloned:last input[name*='parts[name]']").val($(".partsAjaxSearch").text().trim());
      $(".partsAjaxHolder .partsAjaxCloned:last input[name*='parts[id]']").val($(".partsAjaxSearch").val());
      $(".partsAjaxHolder .partsAjaxCloned:last input").removeAttr("id");
      pageAutoLoad(".partsAjaxHolder .partsAjaxCloned:last");
      $(".partsAjaxHolder .partsAjaxCloned:last").removeClass("hide2");
      sortParts();
    });

    $("select[name='paytype']").change(function () {
      $(this).find("option").each(function () {
        $(".Fields_" + $(this).val()).addClass("hide2");
      });
      $(".Fields_" + $(this).val()).removeClass("hide2");
    });

    $("input[name='once[comission]'], select[name='once[comissiontype]'], input[name='total']").change(function () {
      if($("select[name='once[comissiontype]']").val() == "percent"){
        $(".comissionTotal").removeClass("hide2");
        if(parseFloat($("input[name='once[comission]").val()) == 0 || parseFloat($("input[name='total").val()) == 0){
          $(".comissionTotal span").html("0");
          return;
        }
        var commTotal = (parseFloat($("input[name='once[comission]").val())/100)*parseFloat($("input[name='total").val());
        $(".comissionTotal span").html(commTotal);
      }
      else{
        $(".comissionTotal").addClass("hide2");
      }
    });

    $("input[name='once[comission]']").trigger("change");
    $("select[name='paytype']").trigger("change");
    $("select[name='parts[paytype][]']").trigger("change");
    $("input.autoCheckInputsEvent").trigger("change");

    $("#addNewRights").click(function () {
      $(".extraRightsHolder div.alert").remove();
      $(".extraRightsHolder").append($(".extraRightsTemplate").clone());
      $(".extraRightsHolder .extraRightsTemplate:last").addClass("extraRightsCloned").removeClass("extraRightsTemplate");
      $(".extraRightsHolder .extraRightsCloned:last").removeClass("hide2");
      pageAutoLoad(".extraRightsHolder .extraRightsCloned:last");
    });

    $(".buyerAjaxSearch").change(function () {
      if ($(".buyerAjaxSearch").val() == null) {
        return false;
      }
      var duplicatedEntry = false;
      $(".buyerAjaxHolder .buyerAjaxCloned input[name*='buyer[id]']").each(function () {
        if ($(this).val() == $(".buyerAjaxSearch").val()) {
          duplicatedEntry = true;
        }
      });
      if (duplicatedEntry) {
        alert("<?php echo gettext('اضفت هذا المشتري من قبل') ?>");
        return false;
      }
      $(".buyerAjaxHolder").append($(".buyerAjaxTemplate").clone());
      $(".buyerAjaxHolder .buyerAjaxTemplate:last").addClass("buyerAjaxCloned").removeClass("buyerAjaxTemplate");
      $(".buyerAjaxHolder .buyerAjaxCloned:last input[name*='buyer[name]']").val($(".buyerAjaxSearch").text().trim());
      $(".buyerAjaxHolder .buyerAjaxCloned:last input[name*='buyer[id]']").val($(".buyerAjaxSearch").val());
      $(".buyerAjaxHolder .buyerAjaxCloned:last input[name*='buyer[share]']").val(0);
      $(".buyerAjaxHolder .buyerAjaxCloned:last input").removeAttr("id");
      pageAutoLoad(".buyerAjaxHolder .buyerAjaxCloned:last");
      $(".buyerAjaxHolder .buyerAjaxCloned:last").removeClass("hide2");
      $(".buyerAjaxSearch").html("");
    });

    $(".buyerAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن المشتري بالاسم او البريد الإلكتروني') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/buyer/search/",
        dataType: 'json',
        results: function (data) {
          return {results: data};
        },
        data: function (params) {
          return {
            q: params.term,
            callpage: params.page || 1
          }
        }
      }
    });

  });

  function chequeNotnowField(selector) {
    if ($(selector).is(":checked")) {
      $(selector).closest(".autoCheckInputs").find("input[name='parts[cheque_notnow][]']").val("1");
      $(selector).closest(".form-group").find(".chequeNotnowDisabled").addClass("hide2");
      $(selector).closest(".form-group").find(".chequeNotnowEnabled").removeClass("hide2");
    }
    else {
      $(selector).closest(".autoCheckInputs").find("input[name='parts[cheque_notnow][]']").val("0");
      $(selector).closest(".form-group").find(".chequeNotnowEnabled").addClass("hide2");
      $(selector).closest(".form-group").find(".chequeNotnowDisabled").removeClass("hide2");
    }
  }

  function sortParts() {
    $(".partsAjaxCloned").each(function (index) {
      $(this).find("span.partsSort").html((index + 1));
    });
  }

  function partsCommChange(selector) {
    var total = parseFloat($(selector).closest(".partsAjaxCloned").find("input[name='parts[total][]']").val());
    var comm = parseFloat($(selector).closest(".partsAjaxCloned").find("input[name='parts[cash_salecomm][]']").val());
    if (!isNaN(total) && !isNaN(comm)) {
      $(selector).closest(".partsAjaxCloned").find("input[name='parts[cash_commvalue][]']").val((total * (comm / 100)));
    }
  }

  function checkPartsTotal() {
    if ($("input[name='paytype']").val() != "parts") {
      return true;
    }
    var partsTotal = 0;
    var total = parseFloat($("input[name='total']").val());
    $("input[name='parts[total][]']").each(function (index) {
      var partTotal = parseFloat($("input[name='total']").val());
      if (!isNaN(partTotal)) {
        partsTotal += partTotal;
      }
    });
    if (partsTotal != total) {
      alert("<?php echo gettext('مجموع الدفعات لا يساوي اجمالي قيمة البيع للعقار') ?>");
      return false;
    }
    return true;
  }

  function partsPayment(selector) {
    if ($(selector).val() == "cheque") {
      $(selector).closest(".partsAjaxCloned").find(".partsPaymentcash").addClass("hide2");
      $(selector).closest(".partsAjaxCloned").find(".partsPaymentcheque").removeClass("hide2");
    }
    else if ($(selector).val() == "cash") {
      $(selector).closest(".partsAjaxCloned").find(".partsPaymentcheque").addClass("hide2");
      $(selector).closest(".partsAjaxCloned").find(".partsPaymentcash").removeClass("hide2");
    }
    else {
      $(selector).closest(".partsAjaxCloned").find(".partsPaymentcheque").addClass("hide2");
      $(selector).closest(".partsAjaxCloned").find(".partsPaymentcash").addClass("hide2");
    }
  }

  function verifyOwnerShares() {
    var totalshares = 0;
    $(".buyerAjaxCloned input[name*='buyer[share]']").each(function () {
      var share = parseFloat($(this).val());
      totalshares = totalshares + share;
    });
    if (Math.ceil(totalshares) != 100) {
      alert("<?php echo gettext('خطأ! مجموع الحصص للمشتريين لا يساوي 100') ?>");
      return false;
    }
    return checkPartsTotal();
  }
</script>
