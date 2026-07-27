<script type="text/javascript">
  $(document).ready(function () {

    $(".pageType").change(function () {
      if ($(this).val() == 'page') {
        $(".pageTypeLinkout").hide();
        $(".pageTypeLinkin").show();
        $(".pageTypeContent").show();
      }
      else if ($(this).val() == 'linkin') {
        $(".pageTypeContent").hide();
        $(".pageTypeLinkout").hide();
        $(".pageTypeLinkin").show();
      }
      else if ($(this).val() == 'linkout') {
        $(".pageTypeContent").hide();
        $(".pageTypeLinkin").hide();
        $(".pageTypeLinkout").show();
      }
      else if ($(this).val() == 'menu') {
        $(".pageTypeLinkin").hide();
        $(".pageTypeLinkout").hide();
        $(".pageTypeContent").hide();
      }
    });

    $(".js-table-sortable").sortable({
      placeholder: "ui-state-highlight",
      items: "tbody tr",
      handle: ".js-sortable-handle",
      forcePlaceholderSize: true,
      helper: function (e, ui) {
        ui.children().each(function () {
          $(this).width($(this).width());
        });
        return ui;
      },
      start: function (event, ui) {
        ui.placeholder.html('<td colspan="' + $(this).find('tbody tr:first td').size() + '">&nbsp;</td>');
      },
      update: function (event, ui) {
        var sortedIDs = JSON.stringify($(".js-table-sortable").sortable("toArray"));
        $(".smartiocp-loading").show();
        $.post("<?php echo CPURL;?>/<?php echo Module;?>/order/", {
          "no_header": 1,
          "sortedIDs": sortedIDs
        }, function (data) {
          $(".smartiocp-loading").hide();
        });
      }
    });
  });
</script>