<script type="text/javascript">
  $(document).ready(function () {

    $(".buildAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن العقار بالإسم او رقم العقار') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/building/search/?type=rent",
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
      window.location = "<?php echo CPURL;?>/rent/add/?id=" + $(this).val();
    });

    $("input[name='yearlyrent'], select[name='payment']").change(function () {
      var yearlyrent = parseFloat($("input[name='yearlyrent']").val());
      if (isNaN(yearlyrent)) {
        return;
      }
      var cycleRent = yearlyrent/(12/calcCycle($("select[name='payment']").val()));
      if($("select[name='payment']").val() == "month"){
        cycleRent = yearlyrent;
      }
      else if($("select[name='payment']").val() == "day"){
        cycleRent = yearlyrent;
      }
      $("input[name='rent_without_tax']").val(cycleRent);
      $("input[name='rent_without_tax']").trigger("change");
    });

    $("select[name='period']").change(function () {
      if ($(this).val() == "day") {
        $("select[name='payment'] option").eq(0).removeAttr("disabled");
        $("select[name='payment']").val("day");
        $("select[name='payment']").attr("disabled", "disabled");
        $("#periodNumStr").html("<?php echo gettext('يوم') ?>");
        $("#periodRentStr").html("<?php echo gettext('اليومي') ?>");
        if($("input[name='build_rent_day']").length){
          $("input[name='yearlyrent']").val($("input[name='build_rent_day']").val());
        }
        $("select[name='payment']").select2("destroy");
        $("select[name='payment']").select2();
        $("select[name='payment']").trigger("change");
      }
      else if ($(this).val() == "month") {
        $("select[name='payment'] option").eq(1).removeAttr("disabled");
        $("select[name='payment']").val("month");
        $("select[name='payment']").attr("disabled", "disabled");
        $("#periodNumStr").html("<?php echo gettext('شهر') ?>");
        $("#periodRentStr").html("<?php echo gettext('الشهري') ?>");
        if($("input[name='build_rent_month']").length){
          $("input[name='yearlyrent']").val($("input[name='build_rent_month']").val());
        }
        $("select[name='payment']").select2("destroy");
        $("select[name='payment']").select2();
        $("select[name='payment']").trigger("change");
      }
      else {
        var attr = $("select[name='payment'] option").eq(0).attr('selected');
        if (typeof attr !== "undefined" && attr !== false) {
          $("select[name='payment']").val("quartyear");
        } else if($("select[name='payment']")[0].selectedIndex < 2) {
          $("select[name='payment']").val("quartyear");
        }
        $("select[name='payment'] option").eq(0).attr("disabled","disabled");
        $("select[name='payment'] option").eq(1).attr("disabled","disabled");
        $("select[name='payment']").removeAttr("disabled");
        $("#periodNumStr").html("<?php echo gettext('عام') ?>");
        $("#periodRentStr").html("<?php echo gettext('السنوي') ?>");
        if($("input[name='build_rent']").length){
          $("input[name='yearlyrent']").val($("input[name='build_rent']").val());
        }
        $("select[name='payment']").select2("destroy");
        $("select[name='payment']").select2();
        $("select[name='payment']").trigger("change");
      }
    });

    $("input[name='startdate'], input[name='periodnum'], select[name='period']").change(function () {
      if ($("select[name='calendar']").val() == "1") {
        return;
      }
      var startdate = $("input[name='startdate']").val();
      var periodnum = parseFloat($("input[name='periodnum']").val());
      var period = $("select[name='period']").val();
      if (startdate != "" && !isNaN(periodnum) && period != "") {
        var end_date = moment(startdate, 'iYYYY/iM/iD');
        var end_gdate = moment(end_date.format('YYYY-M-D'), 'YYYY-M-D');
        if (period == "year") {
          $("input[name='enddate']").val(end_date.add(periodnum, 'iYear').subtract(1, "days").format('iYYYY/iM/iD'));
        }
        else if (period == "day") {
          $("input[name='enddate']").val(end_gdate.add(periodnum, 'days').subtract(1, "days").format('iYYYY/iM/iD'));
        }
        else {
          $("input[name='enddate']").val(end_date.add(periodnum, 'iMonth').subtract(1, "days").format('iYYYY/iM/iD'));
        }
      }
    });

    $("input[name='startdate'], input[name='periodnum'], select[name='period']").change(function () {
      if ($("select[name='calendar']").val() == "2") {
        return;
      }
      var startdate = $("input[name='startdate']").val();
      var periodnum = parseFloat($("input[name='periodnum']").val());
      var period = $("select[name='period']").val();
      if (startdate != "" && !isNaN(periodnum) && period != "") {
        var end_date = moment(startdate, 'YYYY-M-D');
        if (period == "year") {
          $("input[name='enddate']").val(end_date.add(periodnum, 'Year').subtract(1, "days").format('YYYY-M-D'));
        }
        else if (period == "day") {
          $("input[name='enddate']").val(end_date.add(periodnum, 'Day').subtract(1, "days").format('YYYY-M-D'));
        }
        else {
          $("input[name='enddate']").val(end_date.add(periodnum, 'Month').subtract(1, "days").format('YYYY-M-D'));
        }
      }
    });

    $("input[name='rent_without_tax']").change(function () {
      var rentvalue = parseFloat($("input[name='rent_without_tax']").val());
      if (!isNaN(rentvalue) && parseFloat($("input[name='buildTax']").val()) > 0) {
        var tax = ((rentvalue / 100) * parseFloat($("input[name='buildTax']").val()));
        $("input[name='tax']").val(tax);
        $("input[name='rentvalue']").val(rentvalue + tax);
      }
      else {
        $("input[name='tax']").val("0");
        $("input[name='rentvalue']").val(rentvalue);
      }
    });

    $("input[name='rent_without_tax'], input[name='paidmoney'], input[name='commission'], select[name='comm_cycle']").change(function () {
      var commission = parseFloat($("input[name='commission']").val());
      var comm_cycle = $("select[name='comm_cycle']").val();
      var payment_cycle = $("select[name='payment']").val();
      var rentvalue = parseFloat($("input[name='rentvalue']").val());
      var paidmoney = parseFloat($("input[name='paidmoney']").val());
      var periodnum = parseFloat($("input[name='periodnum']").val());

      if(!isNaN(rentvalue) && !isNaN(commission) && comm_cycle !== ""){
        rentvalue += commission;
      }
      if (!isNaN(rentvalue)) {
        $("input[name='nextpaymentmoney']").val(rentvalue);
      }
      if (!isNaN(rentvalue) && !isNaN(paidmoney)) {
        $("input[name='changemoney']").val(rentvalue - paidmoney);
      }
    });

    $("input[name='rent_without_tax']").change(function () {
      var rentvalue = parseFloat($("input[name='rent_without_tax']").val());
      var tax = parseFloat($("input[name='tax']").val());
      $("input[name='rentvalue']").val(rentvalue + tax);
    });

    $("select[name='payment']").change(function () {
      var selcIndex = $("select[name='payment'] option:selected").index();
      if(selcIndex == 0){
        $("select[name='comm_cycle'] option").attr("disabled", "disabled");
        $("select[name='comm_cycle'] option").eq(0).removeAttr("disabled");
        $("select[name='comm_cycle'] option").eq(1).removeAttr("disabled");
        $("select[name='comm_cycle'] option").eq(2).removeAttr("disabled");
        if($("select[name='comm_cycle'] option:selected").index() > 2){
          $("select[name='comm_cycle']").val("day");
        }
        return true;
      }
      else if(selcIndex == 1){
        $("select[name='comm_cycle'] option").attr("disabled", "disabled");
        $("select[name='comm_cycle'] option").eq(0).removeAttr("disabled");
        $("select[name='comm_cycle'] option").eq(1).removeAttr("disabled");
        $("select[name='comm_cycle'] option").eq(3).removeAttr("disabled");
        if($("select[name='comm_cycle'] option:selected").index() > 3 || $("select[name='comm_cycle']").val() == 'day'){
          $("select[name='comm_cycle']").val("month");
        }
        return true;
      }
      for (var i = 1; i <= $("select[name='comm_cycle'] option").length; i++) {
        if (i > selcIndex+1) {
          $("select[name='comm_cycle'] option").eq(i).removeAttr("disabled");
        }
        else {
          $("select[name='comm_cycle'] option").eq(i).attr("disabled", "disabled");
        }
      }
      $("select[name='comm_cycle'] option").eq(1).removeAttr("disabled");
      if($("select[name='comm_cycle'] option:selected").attr("disabled") == "disabled"){
        $("select[name='comm_cycle']").val("");
      }
    });

    $("select[name='payment'], input[name='startdate']").change(function () {
      var payment = $("select[name='payment']").val();
      var startdate = $("input[name='startdate']").val();
      var calendar = $("select[name='calendar']").val();
      if (calendar == "2" && startdate != "") {
        var cdate = moment(startdate, 'iYYYY/iM/iD');
        var c_gdate = moment(cdate.format('YYYY-M-D'), 'YYYY-M-D');
        if (payment == "day") {
          $("input[name='nextpaymentdate']").val(c_gdate.add($("input[name='periodnum']").val(), 'days').format('iYYYY/iM/iD'));
        }
        else if (payment == "month") {
          $("input[name='nextpaymentdate']").val(cdate.add(1, 'iMonth').format('iYYYY/iM/iD'));
        }
        else if (payment == "quartyear") {
          $("input[name='nextpaymentdate']").val(cdate.add(3, 'iMonth').format('iYYYY/iM/iD'));
        }
        else if (payment == "midyear") {
          $("input[name='nextpaymentdate']").val(cdate.add(6, 'iMonth').format('iYYYY/iM/iD'));
        }
        else if (payment == "year") {
          $("input[name='nextpaymentdate']").val(cdate.add(12, 'iMonth').format('iYYYY/iM/iD'));
        }
      }
      else if (calendar == "1" && startdate != "") {
        var cdate = moment(startdate, 'YYYY-M-D');
        if (payment == "day") {
          $("input[name='nextpaymentdate']").val(cdate.add($("input[name='periodnum']").val(), 'Day').format('YYYY-M-D'));
        }
        else if (payment == "month") {
          $("input[name='nextpaymentdate']").val(cdate.add(1, 'Month').format('YYYY-M-D'));
        }
        else if (payment == "quartyear") {
          $("input[name='nextpaymentdate']").val(cdate.add(3, 'Month').format('YYYY-M-D'));
        }
        else if (payment == "midyear") {
          $("input[name='nextpaymentdate']").val(cdate.add(6, 'Month').format('YYYY-M-D'));
        }
        else if (payment == "year") {
          $("input[name='nextpaymentdate']").val(cdate.add(12, 'Month').format('YYYY-M-D'));
        }
      }
    });

    $("input[name='rent_without_tax'], input[name='commission'], input[name='periodnum'], select[name='payment'], select[name='period'], select[name='comm_cycle']").change(function () {
      var rentvalue = parseFloat($("input[name='rentvalue']").val());
      var periodnum = parseFloat($("input[name='periodnum']").val());
      var commission = parseFloat($("input[name='commission']").val());
      var comm_cycle = $("select[name='comm_cycle']").val();
      var payment_cycle = $("select[name='payment']").val();
      var totalCommission = 0;
      if($("select[name='period']").val() == "year"){
        periodnum = periodnum*12;
      }

      totalCommission = (periodnum/calcCycle(comm_cycle, periodnum))*commission;
      rentvalue = (periodnum/calcCycle(payment_cycle))*rentvalue;

      if(! isNaN(parseFloat(rentvalue + totalCommission))){
        $("input[name='alltotal']").val(parseFloat(rentvalue + totalCommission));
      }
      else if(! isNaN(parseFloat(rentvalue))){
        $("input[name='alltotal']").val(rentvalue);
      }
      else{
        $("input[name='alltotal']").val(0);
      }
    });

    $("select[name='period']").trigger("change");
    $("select[name='calendar']").trigger("change");
    $("input[name='startdate']").trigger("change");
    $("input[name='rent_without_tax']").trigger("change");

    $("#addNewRights").click(function () {
      $(".extraRightsHolder div.alert").remove();
      $(".extraRightsHolder").append($(".extraRightsTemplate").clone());
      $(".extraRightsHolder .extraRightsTemplate:last").addClass("extraRightsCloned").removeClass("extraRightsTemplate");
      $(".extraRightsHolder .extraRightsCloned:last").removeClass("hide2");
      pageAutoLoad(".extraRightsHolder .extraRightsCloned:last");
    });

    $(".buyerAjaxSearch").change(function () {
      var duplicatedEntry = false;
      if ($(".buyerAjaxSearch").val() == null) {
        return false;
      }
      $(".buyerAjaxHolder .buyerAjaxCloned input[name*='buyer[id]']").each(function () {
        if ($(this).val() == $(".buyerAjaxSearch").val()) {
          duplicatedEntry = true;
        }
      });
      if (duplicatedEntry) {
        alert("<?php echo gettext('اضفت هذا المستأجر من قبل') ?>");
        return false;
      }
      $(".buyerAjaxHolder .buyerAjaxCloned").remove();
      $(".buyerAjaxHolder").append($(".buyerAjaxTemplate").clone());
      $(".buyerAjaxHolder .buyerAjaxTemplate:last").addClass("buyerAjaxCloned").removeClass("buyerAjaxTemplate");
      $(".buyerAjaxHolder .buyerAjaxCloned:last input[name*='buyer[name]']").val($(".buyerAjaxSearch option:selected").text().trim());
      $(".buyerAjaxHolder .buyerAjaxCloned:last input[name*='buyer[id]']").val($(".buyerAjaxSearch").val());
      $(".buyerAjaxHolder .buyerAjaxCloned:last input").removeAttr("id");
      pageAutoLoad(".buyerAjaxHolder .buyerAjaxCloned:last");
      $(".buyerAjaxHolder .buyerAjaxCloned:last").removeClass("hide2");
      $(".buyerAjaxSearch").html("");
    });

    $(".buyerAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن المستأجر بالاسم او البريد الإلكتروني') ?>",
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

  function calcCycle(cycle, parent){
    if(cycle == "once"){
      return parent;
    }
    else if(cycle == "day"){
      return 1;
    }
    else if(cycle == "month"){
      return 1;
    }
    else if(cycle == "quartyear"){
      return 3;
    }
    else if(cycle == "midyear"){
      return 6;
    }
    else if(cycle == "year"){
      return 12;
    }
  }
</script>
