<script type="text/javascript">
  $(document).ready(function () {
    $("#addNewRightsModule").click(function () {
      $(".extraRightsHolderModule div.alert").remove();
      $(".extraRightsHolderModule").append($(".extraRightsTemplateModule").clone());
      $(".extraRightsHolderModule .extraRightsTemplateModule:last").addClass("extraRightsClonedModule").removeClass("extraRightsTemplateModule");
      $(".extraRightsHolderModule .extraRightsClonedModule:last").removeClass("hide2");
      pageAutoLoad(".extraRightsHolderModule .extraRightsClonedModule:last");
    });
  });
</script>
