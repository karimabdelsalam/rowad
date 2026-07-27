<script type="text/javascript">
  $(document).ready(function () {

    if ($("#geozone-gmap").length > 0) {
      initializeGMAP("<?php echo $params ?>");
    }

    $("select[name='locid']").change(function () {
      if($(this).val() === "") return;
      $(".smartiocp-loading").show();
      $.get("<?php echo CPURL;?>/building/sublocs/", {
        "locid": $(this).val(), "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        $("select[name='copyfrom']").html(data);
      });
    });
    $("select[name='locid']").trigger("change")

    $("select[name='buildcat']").change(function () {
      $(".smartiocp-loading").show();
      $.get("<?php echo CPURL;?>/building/custom_fields/", {
        "catid": $(this).val(), "buildid": "<?php echo $_GET['id'] ?>", "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        $("#cfields").html(data);
        $.switcher("#cfields .ckeckonoff");
      });
    });
    if($("select[name='buildcat']").val()) {
      $("select[name='buildcat']").trigger("change")
    }

    $("select[name='copyfrom']").change(function () {
      window.location = "<?php echo CPURL;?>/building/add/?copyfrom="+$(this).val();
    });
    $("select[name='locid']").change(function () {
      if("<?php echo Action;?>" === "add" && confirm("<?php echo gettext('هل تريد نسخ البيانات من هذا العقار ؟') ?>")){
        window.location = "<?php echo CPURL;?>/building/add/?locid="+$(this).val();
      }
    });

    $("select[name='type']").change(function () {
      if ($(this).val() == "rent") {
        $(".buildForSale").hide();
        $(".buildForRent").show();
      }
      else {
        $(".buildForRent").hide();
        $(".buildForSale").show();
      }
    });

    $("select[name='tax']").change(function () {
      if ($(this).val() == "habit") {
        $("select[name='buyercat']").closest("div").show();
      }
      else {
        $("select[name='buyercat']").closest("div").hide();
      }
    });

    $("select[name='period']").change(function () {
      if ($(this).val() == "year") {
        $("select[name='payment']").closest("div").removeClass("hide2");
      }
      else {
        $("select[name='payment']").closest("div").addClass("hide2");
      }
    });

    $("select[name='tax']").trigger("change");
    $("select[name='type']").trigger("change");
    $("select[name='period']").trigger("change");
    $("select[name*='plots']").trigger("onchange");

    $(".ownerAjaxSearch").change(function () {
      if ($(".ownerAjaxSearch").val() == null) {
        return false;
      }
      var duplicatedEntry = false;
      $(".ownerAjaxHolder .ownerAjaxCloned input[name*='owner[id]']").each(function () {
        if ($(this).val() == $(".ownerAjaxSearch").val()) {
          duplicatedEntry = true;
        }
      });
      if (duplicatedEntry) {
        alert("<?php echo gettext('اضفت هذا المالك من قبل') ?>");
        return false;
      }
      $(".ownerAjaxHolder").append($(".ownerAjaxTemplate").clone());
      $(".ownerAjaxHolder .ownerAjaxTemplate:last").addClass("ownerAjaxCloned").removeClass("ownerAjaxTemplate");
      $(".ownerAjaxHolder .ownerAjaxCloned:last input[name*='owner[name]']").val($(".ownerAjaxSearch option:selected").text().trim());
      $(".ownerAjaxHolder .ownerAjaxCloned:last input[name*='owner[id]']").val($(".ownerAjaxSearch").val());
      //$(".ownerAjaxHolder .ownerAjaxCloned:last input[name*='owner[share]']").val(0);
      $(".ownerAjaxHolder .ownerAjaxCloned:last input").removeAttr("id");
      pageAutoLoad(".ownerAjaxHolder .ownerAjaxCloned:last");
      $(".ownerAjaxHolder .ownerAjaxCloned:last").removeClass("hide2");
      $(".ownerAjaxSearch").html("");
    });

    $(".ownerAjaxSearch, .ownerAjaxSelcSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن مالك بالاسم او البريد الإلكتروني') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/owner/search/",
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

  function addNewBid() {
    $(".bidsAjaxHolder div.alert").remove();
    $(".bidsAjaxHolder").append($(".bidsAjaxHolder div.bidsAjaxTemplate").clone());
    $(".bidsAjaxHolder div.bidsAjaxTemplate:last").addClass("bidsAjaxCloned");
    $(".bidsAjaxHolder div.bidsAjaxCloned:last").removeClass("hide2");
    $(".bidsAjaxHolder div.bidsAjaxCloned:last").removeClass("bidsAjaxTemplate");
    pageAutoLoad(".bidsAjaxHolder div.bidsAjaxCloned:last");
    $("select[name*='bids']").trigger("onchange");
  }

  function addNewPlot() {
    $(".plotInfoHolder div.alert").remove();
    $(".plotInfoHolder").append($(".plotInfoTable").clone());
    $(".plotInfoHolder table:last").removeClass("hide2");
    $(".plotInfoHolder table:last").removeClass("plotInfoTable");
    pageAutoLoad(".plotInfoHolder table:last");
    $("select[name*='plots']").trigger("onchange");
  }

  function addNewDead() {
    $(".deedInfoHolder div.alert").remove();
    $(".deedInfoHolder").append($(".deedInfoHolder div.deadInfoTemplate").clone());
    $(".deedInfoHolder div.deadInfoTemplate:last").addClass("deadInfoTable");
    $(".deedInfoHolder div.deadInfoTable:last").removeClass("hide2");
    $(".deedInfoHolder div.deadInfoTable:last").removeClass("deadInfoTemplate");
    pageAutoLoad(".deedInfoHolder div.deadInfoTable:last");
    $("select[name*='deed']").trigger("onchange");
  }

  function addNewMeter() {
    $(".metersInfoHolder div.alert").remove();
    $(".metersInfoHolder").append($(".metersInfoHolder div.metersInfoTemplate").clone());
    $(".metersInfoHolder div.metersInfoTemplate:last").addClass("metersInfoTable");
    $(".metersInfoHolder div.metersInfoTable:last").removeClass("hide2");
    $(".metersInfoHolder div.metersInfoTable:last").removeClass("metersInfoTemplate");
    pageAutoLoad(".metersInfoHolder div.metersInfoTable:last");
    $("select[name*='meters']").trigger("onchange");
  }

  function plotChange(elm) {
    var tr = $(elm).closest('tr');
    var attrs = $(elm).find("option:selected").attr("data-attrs");
    $(elm).closest('tr').find("div.plotWidth").hide();
    $(elm).closest('tr').find("div.plotNo").hide();
    $(elm).closest('tr').find("div.plotLength").hide();
    if (attrs.indexOf("N") >= 0) {
      $(elm).closest('tr').find("div.plotNo").show();
    }
    if (attrs.indexOf("W") >= 0) {
      $(elm).closest('tr').find("div.plotWidth").show();
    }
    if (attrs.indexOf("L") >= 0) {
      $(elm).closest('tr').find("div.plotLength").show();
    }
  }

  function verifyOwnerShares() {
    var totalshares = 0;
    $(".ownerAjaxCloned input[name*='owner[share]']").each(function () {
      var share = parseFloat($(this).val());
      totalshares = totalshares + share;
    });
    if (Math.ceil(totalshares) != 100) {
      alert("<?php echo gettext('خطأ! مجموع الحصص للملاك لا يساوي 100') ?>");
      return false;
    }
    return verifyOwnerOutgoings();
  }

  function verifyOwnerOutgoings() {
    var total = 0;
    $(".ownerAjaxCloned input[name*='owner[outgoings]']").each(function () {
      var share = parseFloat($(this).val());
      total = total + share;
    });
    if (Math.ceil(total) != 100) {
      alert("<?php echo gettext('خطأ! مجموع توزيع المصاريف على الملاك لا يساوي 100') ?>");
      return false;
    }
    return true;
  }
</script>
