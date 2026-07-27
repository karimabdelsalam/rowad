<script type="text/javascript">
  $(document).ready(function () {

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
      }
    });
    $(".buyerSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن المستأجر او المشتري بالإسم') ?>",
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

    <?php if(!empty($params[0]['report'])): ?>
    if(typeof Chart != "undefined"){
      new Chart($("#horizontalBar"), {
        type: "horizontalBar",
        data: {
          labels: [<?php foreach($params[0]['report']['debits'] as $type) echo '\''.gettext('عقد رقم').' '.$type['contractid'].' ('.(($type['module'] == 'rent')?gettext('ايجار'):gettext('بيع')).')\','; ?>],
          datasets: [{
            label: "<?php echo gettext('مديونيات') ?>",
            data: [<?php foreach($params[0]['report']['debits'] as $type) echo "($type[counterValue]),"; ?>],
            fill: false,
            backgroundColor: [<?php foreach($params[0]['report']['debits'] as $type) echo 'randomColor("0.2"),'; ?>],
            borderColor: [<?php foreach($params[0]['report']['debits'] as $type) echo '"rgb(0, 0, 0, 0.2)",'; ?>],
            borderWidth: 1
          }]
        },
        options: {
          scales: {
            xAxes: [{
              ticks: {
                beginAtZero: true
              }
            }]
          }
        }
      });
    }
    <?php else: ?>
    $(".chartGraphs").remove();
    <?php endif; ?>
  });

  $('#choiceModal').on('hidden.bs.modal', function () {
    $('#choiceModal button.choiceModalYes').unbind("click");
  });

  function showStatinModal(paymentid) {
    $('#choiceModal .modal-title').html("<?php echo gettext('إصدار سند قبض') ?>");
    $('#choiceModal .modal-body p').html("<?php echo gettext('هل تريد إصدار سند قبض للمبلغ المستلم والمرحل ؟') ?>");
    $('#choiceModal').modal();
    $('#choiceModal button.choiceModalYes').click(function () {
      $('#choiceModal').modal("hide");
      $(this).unbind("click");
      $(".quick-form-trigger").attr("href", "<?php echo CPURL ?>/statementsin/add/?offerprint=1&refid=" + paymentid).trigger("click");
    });
  }
  function showStatinPrintModal(statid) {
    $('#choiceModal .modal-title').html("<?php echo gettext('طباعة سند قبض') ?>");
    $('#choiceModal .modal-body p').html("<?php echo gettext('هل تريد طباعة سند القبض الذي تم تحريره الآن ؟') ?>");
    $('#choiceModal').modal();
    $('#choiceModal button.choiceModalYes').click(function () {
      $('#choiceModal').modal("hide");
      $(this).unbind("click");
      var win = window.open("<?php echo CPURL ?>/statementsin/printable/?id=" + statid, '_blank');
      win.focus();
    });
  }
</script>
