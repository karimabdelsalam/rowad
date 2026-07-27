<script type="text/javascript">
  $(document).ready(function () {

    if(typeof jQuery.fn.select2 != "undefined"){
      $(".buildingAjaxSearch").select2({
        minimumInputLength: 0,
        placeholder: "<?php echo gettext('ابحث عن العقار بالاسم') ?>",
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
    }

  <?php if(!empty($params[0]['report'])): ?>
    new Chart($("#pieChart"), {
      type: 'pie',
      data:{
        labels: ["<?php echo gettext('مستغل') ?>", "<?php echo gettext('متاح') ?>"],
        datasets: [{
          backgroundColor: [ "rgb(112,206,157)", "rgb(222,155,155)" ],
          data: [<?php echo $params[0]['report']['busyMonths'].",".$params[0]['report']['freeMonths']; ?>]
        }],
      },
      options: {
        title: {
          display: true,
          text: "<?php echo gettext('نسبة الإشغال') ?>"
        }
      }
    });

    new Chart($("#pieChart2"), {
      type: 'pie',
      data:{
        labels: ["<?php echo gettext('إيرادات') ?>", "<?php echo gettext('مصاريف') ?>"],
        datasets: [{
          backgroundColor: [ "rgb(112,206,157)", "rgb(222,155,155)" ],
          data: [<?php echo $params[0]['report']['income'].",".$params[0]['report']['outcome']; ?>]
        }],
      },
      options: {
        title: {
          display: true,
          text: "<?php echo gettext('نسبة الأرباح') ?>"
        }
      }
    });

    new Chart($("#horizontalBar1"), {
      type: "horizontalBar",
      data: {
        labels: [<?php foreach($params[0]['report']['contracts'] as $type) echo '\''.gettext('عقد رقم').' '.$type['contractid'].'\','; ?>],
        datasets: [{
          label: "<?php echo gettext('إيرادات العقود') ?>",
          data: [<?php foreach($params[0]['report']['contracts'] as $type) echo "($type[counterValue]),"; ?>],
          fill: false,
          backgroundColor: [<?php foreach($params[0]['report']['contracts'] as $type) echo 'randomColor("0.2"),'; ?>],
          borderColor: [<?php foreach($params[0]['report']['contracts'] as $type) echo '"rgb(0, 0, 0, 0.2)",'; ?>],
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

    new Chart($("#horizontalBar2"), {
      type: "horizontalBar",
      data: {
        labels: [<?php foreach($params[0]['report']['contracts'] as $type) echo '\''.gettext('عقد رقم').' '.$type['contractid'].'\','; ?>],
        datasets: [{
          label: "<?php echo gettext('٢٠ يوم تأخر في السداد') ?>",
          data: [<?php foreach($params[0]['report']['delays'] as $type) echo "$type[counterValue],"; ?>],
          fill: false,
          backgroundColor: [<?php foreach($params[0]['report']['delays'] as $type) echo 'randomColor("0.2"),'; ?>],
          borderColor: [<?php foreach($params[0]['report']['delays'] as $type) echo '"rgb(0, 0, 0, 0.2)",'; ?>],
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

    new Chart($("#horizontalBar3"), {
      type: "horizontalBar",
      data: {
        labels: [<?php foreach($params[0]['report']['contracts'] as $type) echo '\''.gettext('عقد رقم').' '.$type['contractid'].'\','; ?>],
        datasets: [{
          label: "<?php echo gettext('مديونيات') ?>",
          data: [<?php foreach($params[0]['report']['debits'] as $type) echo "$type[counterValue],"; ?>],
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

    <?php else: ?>
    $(".chartGraphs").remove();
    <?php endif; ?>

  });
</script>
