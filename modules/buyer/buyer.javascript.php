<script type="text/javascript">
  $(document).ready(function () {

    $("select[name='type']").change(function () {
      if ($(this).val() == "person") {
        $(".companyFields").addClass("hide2");
      }
      else {
        $(".companyFields").removeClass("hide2");
      }
    });
    $("select[name='type']").trigger("change");

    $("#copyMobileNumber").click(function (e) {
      $("input[name='username']").val($("input[name='mobile']").val());
    });

  });
</script>
