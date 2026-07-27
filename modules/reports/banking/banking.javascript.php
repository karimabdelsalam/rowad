<?php if(!empty($params[0]['report'])): ?>
<script type="text/javascript">
$(document).ready(function () {

  new Chart($("#pieChart"), {
    type: 'pie',
    data:{
      labels: ["<?php echo gettext('دائن') ?>", "<?php echo gettext('مدين') ?>"],
      datasets: [{
        backgroundColor: [ "rgb(112,206,157)", "rgb(222,155,155)" ],
        data: [<?php echo $params[0]['report']['credit']['totalValue'].",".$params[0]['report']['debit']['totalValue']; ?>]
      }],
    },
  });

  new Chart($("#horizontalBar"), {
    type: "horizontalBar",
    data: {
      labels: [<?php foreach($params[0]['report']['accounts'] as $type) echo "'$type[fullname] ($type[accno])',"; ?>],
      datasets: [{
        label: "<?php echo gettext('عدد الحركات') ?>",
        data: [<?php foreach($params[0]['report']['accounts'] as $type) echo "$type[counterValue],"; ?>],
        fill: false,
        backgroundColor: [<?php foreach($params[0]['report']['accounts'] as $type) echo 'randomColor("0.2"),'; ?>],
        borderColor: [<?php foreach($params[0]['report']['accounts'] as $type) echo '"rgb(0, 0, 0, 0.2)",'; ?>],
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
