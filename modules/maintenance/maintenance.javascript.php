<script type="text/javascript">
  $(document).ready(function () {
    $(".do-progress").click(function (e) {
      e.preventDefault();
      var href = $(this).attr('href');
      var bar = $(this).attr('data-bar');
      var element = this;
      $(".smartiocp-loading").show();
      $.get(href, {
        "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        if (data >= 100) {
          data = 100;
        }
        $("." + bar).animate({width: data + "%"}, "fast", function () {
          if (data < 100) {
            $(element).trigger("click");
          }
        });
      });
    });
  });
</script>