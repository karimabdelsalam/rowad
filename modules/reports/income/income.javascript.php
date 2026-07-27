<?php if(!empty($params[0]['report'])): ?>
<script type="text/javascript">
$(document).ready(function () {

  new Chart($("#pieChart"), {
    type: 'pie',
    data:{
      labels: [<?php foreach($params[0]['report']['from_type'] as $type) echo "'$type[title]',"; ?>],
      datasets: [{
        backgroundColor: [<?php foreach($params[0]['report']['from_type'] as $type) echo 'randomColor("0.6"),'; ?>],
        data: [<?php foreach($params[0]['report']['from_type'] as $type) echo "$type[counterValue],"; ?>]
      }],
    },
  });

  new Chart($("#pieChart2"), {
    type: 'pie',
    data:{
      labels: [<?php foreach($params[0]['report']['pay_method'] as $type) echo "'".core::payMethodTitle($type['title'])."',"; ?>],
      datasets: [{
        backgroundColor: [<?php foreach($params[0]['report']['pay_method'] as $type) echo 'randomColor("0.6"),'; ?>],
        data: [<?php foreach($params[0]['report']['pay_method'] as $type) echo "$type[counterValue],"; ?>]
      }],
    }
  });

  new Chart($("#horizontalBar"), {
    type: "horizontalBar",
    data: {
      labels: [<?php foreach($params[0]['report']['typeid'] as $type) echo "'$type[title]',"; ?>],
      datasets: [{
        label: "<?php echo gettext('انواع الإيرادات') ?>",
        data: [<?php foreach($params[0]['report']['typeid'] as $type) echo "$type[counterValue],"; ?>],
        fill: false,
        backgroundColor: [<?php foreach($params[0]['report']['typeid'] as $type) echo 'randomColor("0.2"),'; ?>],
        borderColor: [<?php foreach($params[0]['report']['typeid'] as $type) echo '"rgb(0, 0, 0, 0.2)",'; ?>],
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

});
</script>
<?php else: ?>
<script type="text/javascript">
  $(document).ready(function () {
    $(".chartGraphs").remove();
  });
</script>
<?php endif; ?>
