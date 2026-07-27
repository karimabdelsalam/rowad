<script type="text/javascript">
  $(document).ready(function () {
    var searchBuildsConfig = {
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن العقار بالإسم او رقم العقار') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/building/search/",
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
    };
    $(".buildAjaxSearch").select2();

    $("input[name='buildexpenses']").change(function () {
      if($(this).val() == 1){
        $(".expensesChoice").removeClass("hide2");
      } else {
        $(".expensesChoice").addClass("hide2");
      }
    });
    $("input[name='buildexpenses']").trigger("change");

    $("select[name='pay_method']").change(function () {
      $(this).find("option").each(function () {
        $(".StatementPayMethod_" + $(this).val()).addClass("hide2");
      });
      $(".StatementPayMethod_" + $(this).val()).removeClass("hide2");
    });
    $("select[name='pay_method']").trigger("change");

    $("select[name='from_type']").change(function () {
      $(this).find("option").each(function () {
        $(".StatementFromType_" + $(this).val()).addClass("hide2");
      });
      $(".StatementFromType_" + $(this).val()).removeClass("hide2");
      if ($(this).val() == "buildowner" || $(this).val() == "buildrenter" || $(this).val() == "buildbuyer") {
        $(".buildAjaxSearch").select2("destroy");
        $(".buildAjaxSearch").attr("required", "required");
        $(".buildAjaxSearch").select2();
      }
      else {
        $(".buildAjaxSearch").select2("destroy");
        $(".buildAjaxSearch").removeAttr("required", "required");
        $(".buildAjaxSearch").select2(searchBuildsConfig);
      }
    });
    $("select[name='from_type']").trigger("change");

    $("select[name='buildownerid']").change(function () {
      $(".smartiocp-loading").show();
      $.get("<?php echo CPURL;?>/building/search/", {
        "ownerid": $(this).val(), "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        $("select[name='buildid']").html(data);
        $(".buildAjaxSearch").select2("destroy");
        $(".buildAjaxSearch").select2();
      });
    });
    $("select[name='renterid']").change(function () {
      $(".smartiocp-loading").show();
      $.get("<?php echo CPURL;?>/building/search/", {
        "renterid": $(this).val(), "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        $("select[name='buildid']").html(data);
        $(".buildAjaxSearch").select2("destroy");
        $(".buildAjaxSearch").select2();
      });
    });
    $("select[name='buyerid']").change(function () {
      $(".smartiocp-loading").show();
      $.get("<?php echo CPURL;?>/building/search/", {
        "buyerid": $(this).val(), "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        $("select[name='buildid']").html(data);
        $(".buildAjaxSearch").select2("destroy");
        $(".buildAjaxSearch").select2();
      });
    });

    $("input[name='transid']").change(function () {
      if ($(this).val() == "") return;
      $(".smartiocp-loading").show();
      $.get("<?php echo CPURL;?>/<?php echo Module;?>/transinfo/", {
        "transid": $(this).val(), "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        if (data == 1) {
          var info = '<span class="label label-danger"><?php echo gettext('لا يوجد بيانات حول تلك العملية') ?></span>';
          $("input[name='transid']").val("0");
        }
        else {
          var payment = JSON.parse(data);
          var totalAmount = parseFloat(payment["amount"]);
          var info = '<span class="label label-success">' + payment["transtype"] + '</span> <span class="label label-primary">' + payment["paydate"] + '</span> <span class="label label-default">' + payment["build"] + '</span> <span class="label label-primary">' + totalAmount + ' <?php echo $this->config['currency']?></span>';
          if ($("input[name='amount']").val() == "") {
            $("input[name='amount']").val(totalAmount);
          }
          $(".buildAjaxSearch").append('<option value="' + payment["buildid"] + '">' + payment["build"] + '</option>').val(payment["buildid"]).trigger("change");
          $(".buildAjaxSearch").attr("required", "required");
        }
        $(".paymentInfo").html(info);
      });
    });
    $("input[name='transid']").trigger("change");

    $(".employeeAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن الموظف بالاسم او رقم الهوية') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/employees/search/",
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
    $(".buildownerAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن المالك بالاسم') ?>",
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
    $(".buildrenterAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن المستأجر بالاسم') ?>",
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
    $(".buildbuyerAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن المشتري بالاسم') ?>",
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
    $(".govorgAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن الجهة الحكومية بالاسم') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/govorgs/search/",
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
    $(".citizenAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن مواطن بالاسم') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/citizen/search/",
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
    $(".comorgAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن الجهة التجارية بالاسم') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/tradeorgs/search/",
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
</script>
