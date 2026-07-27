<script type="text/javascript">
  function showStatoutModal(params) {
    $('#choiceModal .modal-title').html("<?php echo gettext('إصدار سند صرف') ?>");
    $('#choiceModal .modal-body p').html("<?php echo gettext('هل تريد إصدار سند صرف بالمبلغ المرحل ؟') ?>");
    $('#choiceModal').modal();
    $('#choiceModal button.choiceModalYes').click(function () {
      $('#choiceModal').modal("hide");
      $(this).unbind("click");
      $(".quick-form-trigger").attr("href", "<?php echo CPURL ?>/statementsout/add/?offerprint=1&refid=" + params.transid + "&ownerid=" + params.ownerid).trigger("click");
    });
  }
  function showStatoutPrintModal(statid) {
    $('#choiceModal .modal-title').html("<?php echo gettext('طباعة سند صرف') ?>");
    $('#choiceModal .modal-body p').html("<?php echo gettext('هل تريد طباعة سند الصرف الذي تم تحريره الآن ؟') ?>");
    $('#choiceModal').modal();
    $('#choiceModal button.choiceModalYes').click(function () {
      $('#choiceModal').modal("hide");
      $(this).unbind("click");
      var win = window.open("<?php echo CPURL ?>/statementsout/printable/?id=" + statid, '_blank');
      win.focus();
    });
  }

  $(document).ready(function () {
    $('#choiceModal').on('hidden.bs.modal', function () {
      $('#choiceModal button.choiceModalYes').unbind("click");
    });

    $(".buildAjaxSearch").select2({
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
      },

    });
  });
</script>
