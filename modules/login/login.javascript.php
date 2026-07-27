<script type="text/javascript">
  $(document).ready(function () {
    $(".show-toggle-form").click(function () {
      var form = $(this).attr("data-form");
      $(".login-form-toggle").addClass("hide2");
      $("." + form).removeClass("hide2");
    });
    if (window.location.hash == "#signup") {
      $(".login-form-login").addClass("hide2");
      $(".login-form-register").removeClass("hide2");
    }
  });
</script>