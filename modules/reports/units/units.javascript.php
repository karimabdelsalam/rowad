<?php if(!empty($params['builds_timeline']) || !empty($params['contracts_timeline'])): ?>
<script type="text/javascript">
$(document).ready(function () {

  new Chart($("#horizontalYearBar"), {
    type: "bar",
    data: {
      labels: ["<?php echo gettext('يناير') ?>", "<?php echo gettext('فبراير') ?>", "<?php echo gettext('مارس') ?>", "<?php echo gettext('ابريل') ?>", "<?php echo gettext('مايو') ?>", "<?php echo gettext('يونيو') ?>", "<?php echo gettext('يوليو') ?>", "<?php echo gettext('اغسطس') ?>", "<?php echo gettext('سبتمبر') ?>", "<?php echo gettext('اكتوبر') ?>", "<?php echo gettext('نوفمبر') ?>", "<?php echo gettext('ديسمبر') ?>"],
      datasets: [{
        label: "<?php echo gettext('عقارات تمت اضافتها خلال عام').' '.$_GET['year'] ?>",
        data: [<?php for($month=1;$month<=12;$month++) if(!empty($params['builds_timeline'][$month])) echo $params['builds_timeline'][$month].","; ?>],
        fill: false,
        backgroundColor: ["rgba(255, 99, 132, 0.2)", "rgba(255, 159, 64, 0.2)", "rgba(255, 205, 86, 0.2)", "rgba(75, 192, 192, 0.2)", "rgba(54, 162, 235, 0.2)", "rgba(153, 102, 255, 0.2)", "rgba(201, 203, 207, 0.2)", "rgba(217, 219, 10, 0.2)", "rgba(247, 146, 96, 0.2)", "rgba(180, 217, 152, 0.2)", "rgba(136, 244, 128, 0.2)", "rgba(210, 12, 141, 0.2)"],
        borderColor: ["rgb(255, 99, 132)", "rgb(255, 159, 64)", "rgb(255, 205, 86)", "rgb(75, 192, 192)", "rgb(54, 162, 235)", "rgb(153, 102, 255)", "rgb(201, 203, 207)", "rgba(217, 219, 10)", "rgba(247, 146, 96)", "rgba(180, 217, 152)", "rgba(136, 244, 128)", "rgba(210, 12, 141)"],
        borderWidth: 1
      }]
    },
    options: {
      plugins: {
        datalabels: {
          display: true,
          anchor: "center",
          textAlign: "center",
          align: "center"
        }
      },
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
