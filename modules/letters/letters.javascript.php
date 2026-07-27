<script type="text/javascript">
  $(document).ready(function () {

    $("select[name='type']").change(function () {
      $(this).find("option").each(function () {
        $(".LetterType_" + $(this).val()).addClass("hide2");
      });
      $(".LetterType_" + $(this).val()).removeClass("hide2");
    });
    $("select[name='type']").trigger("change");

    $("select[name='renterid']").change(function () {
      $(".smartiocp-loading").show();
      $.get("<?php echo CPURL;?>/building/search/", {
        "renterid": $(this).val(), "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        $("select[name='buildid']").html(data);
      });
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

    $("select[name*='review[plot]']").trigger("onchange");

  });

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
</script>
