<script type="text/javascript">
  $(document).ready(function () {
    function formatState (state) {
      if (!state.id) { return state.text; }
      return $(
        `<span class="font-select"><i class="faback faback-2x faback-${state.element.value.toLowerCase()}"></i> <p>${state.text}</p></span>`
      );
    }

    $(".js-select-templating").select2({
      placeholder: "<?php echo gettext('اختر شكل الرمز ليظهر بجانب اسم الحقل') ?>",
      templateResult: formatState,
      templateSelection: formatState
    });

  });
</script>
