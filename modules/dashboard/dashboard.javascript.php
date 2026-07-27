<?php
if(!empty($params)){
  $stat_date = array();
  $stat_date['start'] = strtotime($params['fdate']);
  $stat_date['end'] = strtotime($params['todate']);
  $timeRange = ($stat_date['end']-$stat_date['start'])/2592000;
  if($timeRange > 3 && $timeRange <= 13){
    $timeLineRange = ['interval' => 1, 'age' => 'month', 'format' => '%m/%y'];
  } elseif($timeRange > 12){
    $timeLineRange = ['interval' => 6, 'age' => 'month', 'format' => '%m/%y'];
  } else {
    $timeLineRange = ['interval' => 4, 'age' => 'day', 'format' => '%d/%m'];
  }
?>
  <script type="text/javascript">
    $(document).ready(function () {
      var charts = {
        // utility class
        utility: {
          chartColors: ["#ccccc", "#999", "#CC3A3A", "#DDD", "#EEE", "#999"],
          //chartBackgroundColors: ["transparent", "transparent"],
          chartBackgroundColors: ["rgba(255,255,255,0)", "rgba(255,255,255,0)", "rgba(255,255,255,0)"],
          applyStyle: function (that) {
            that.options.colors = charts.utility.chartColors;
            that.options.grid.backgroundColor = {
              colors: charts.utility.chartBackgroundColors
            };
            that.options.grid.borderColor = charts.utility.chartColors[0];
            that.options.grid.color = charts.utility.chartColors[0];
          },
        }
      };

      var options = {
        grid: {
          show: true,
          aboveData: true,
          color: "#3f3f3f",
          labelMargin: 5,
          axisMargin: 0,
          borderWidth: 0,
          borderColor: null,
          minBorderMargin: 5,
          clickable: true,
          hoverable: true,
          autoHighlight: true,
          mouseActiveRadius: 20,
          backgroundColor: {}
        },
        series: {
          grow: {
            active: false
          },
          bars: {
            show: true,
            fill: true,
            lineWidth: 2,
            barWidth: 0.3,
            align: "center",
            order: 1,
          },
          points: {
            show: false
          }
        },
        legend: {
          show: true,
          position: ("<?php echo $_SESSION['lang']['code'] ?>" == "ar")? "nw" : "ne",
          backgroundColor: null,
          backgroundOpacity: 0
        },
        yaxis: {
          min: 0
        },
        xaxis: {
          mode: null,
          tickDecimals: 0,
          ticks: [[1, "<?php echo gettext('يناير') ?>"], [2, "<?php echo gettext('فبراير') ?>"], [3, "<?php echo gettext('مارس') ?>"], [4, "<?php echo gettext('ابريل') ?>"], [5, "<?php echo gettext('مايو') ?>"], [6, "<?php echo gettext('يونيو') ?>"], [7, "<?php echo gettext('يوليو') ?>"], [8, "<?php echo gettext('اغسطس') ?>"], [9, "<?php echo gettext('سبتمبر') ?>"], [10, "<?php echo gettext('اكتوبر') ?>"], [11, "<?php echo gettext('نوفمبر') ?>"], [12, "<?php echo gettext('ديسمبر') ?>"] ],
          //ticks: [1,2,3,4,5,6,7,8,9,10,11,12],
        },
        colors: [],
        shadowSize: 1,
        tooltip: true,
        tooltipOpts: {
          content: "%x - %y.0 <?php echo $this->config['currency'] ?> %s",
          shifts: {
            x: -30,
            y: -50
          },
          defaultTheme: false
        }
      };

      (function ($) {
        if (typeof charts == 'undefined') return;

        charts.stat_finance_chart_profits = {
          data: [],
          // will hold the chart object
          plot: null,
          options: options,
          placeholder: "#stat_finance_chart_profits",
          // initialize
          init: function () {
            charts.utility.applyStyle(this);
            this.data = [
              <?php
              if(!empty($params['profits'])){
                foreach($params['profits'] as $month => $profit){
                  echo '[' . $month . ', ' . $profit . '],';
                }
              } else {
                echo '0, 0';
              }
              ?>
            ];
            this.plot = $.plot(
              this.placeholder, [{
                label: ["<?php echo gettext('ارباح') ?>"],
                data: this.data,
                color: "#8BBF61"
              }], this.options);
          }
        };

        charts.stat_finance_chart_incomes = {
          data: [],
          // will hold the chart object
          plot: null,
          options: options,
          placeholder: "#stat_finance_chart_incomes",
          // initialize
          init: function () {
            charts.utility.applyStyle(this);
            this.data = [
              <?php
              if(!empty($params['income'])){
                foreach($params['income'] as $month => $income){
                  echo '[' . $month . ', ' . $income . '],';
                }
              } else {
                echo '0, 0';
              }
              ?>
            ];
            this.plot = $.plot(
              this.placeholder, [{
                label: ["<?php echo gettext('ايرادات') ?>"],
                data: this.data,
                color: "#4886c8"
              }],
              this.options);
          }
        };

        charts.stat_finance_chart_outcomes = {
          data: [],
          // will hold the chart object
          plot: null,
          options: options,
          placeholder: "#stat_finance_chart_outcomes",
          // initialize
          init: function () {
            charts.utility.applyStyle(this);
            this.data = [
              <?php
              if(!empty($params['outcome'])){
                foreach($params['outcome'] as $month => $outcome){
                  echo '[' . $month . ', ' . $outcome . '],';
                }
              } else {
                echo '0, 0';
              }
              ?>
            ];
            this.plot = $.plot(
              this.placeholder, [{
                label: ["<?php echo gettext('مصروفات') ?>"],
                data: this.data,
                color: "#c83b3e"
              }],
              this.options);
          }
        };

        charts.stat_finance_chart_all = {
          data: { d1: {}, d2: {}, d3: {} },
          // will hold the chart object
          plot: null,
          options: options,
          placeholder: "#stat_finance_chart_all",
          // initialize
          init: function () {
            charts.utility.applyStyle(this);
            this.data.d1 = [
              <?php
              if(!empty($params['profits'])){
                foreach($params['profits'] as $month => $outcome){
                  echo '[' . $month . ', ' . $outcome . '],';
                }
              } else {
                echo '0, 0';
              }
              ?>
            ];
            this.data.d2 = [
              <?php
              if(!empty($params['income'])){
                foreach($params['income'] as $month => $outcome){
                  echo '[' . $month . ', ' . $outcome . '],';
                }
              } else {
                echo '0, 0';
              }
              ?>
            ];
            this.data.d3 = [
              <?php
              if(!empty($params['outcome'])){
                foreach($params['outcome'] as $month => $outcome){
                  echo '[' . $month . ', ' . $outcome . '],';
                }
              } else {
                echo '0, 0';
              }
              ?>
            ];
            this.plot = $.plot(
              this.placeholder, [
                {
                  label: ["<?php echo gettext('ارباح') ?>"],
                  data: this.data.d1,
                  color: "#8BBF61"
                },
                {
                  label: ["<?php echo gettext('إيرادات') ?>"],
                  data: this.data.d2,
                  color: "#4886c8"
                },
                {
                  label: ["<?php echo gettext('مصروفات') ?>"],
                  data: this.data.d3,
                  color: "#c83b3e"
                }
              ],
              this.options);
          }
        };

        charts.stat_finance_chart_profits.init();
        charts.stat_finance_chart_incomes.init();
        charts.stat_finance_chart_outcomes.init();
        charts.stat_finance_chart_all.init();
      })(jQuery);

      $(".stat-tab-content div.tab-pane").removeClass("active");
      $(".stat-tab-content div.tab-pane:first").addClass("active");
    });
  </script>
<?php } else { ?>
  <script type="text/javascript">
    $(document).ready(function () {
      $(".stat-tab-content div.tab-pane").removeClass("active");
      $(".stat-tab-content div.tab-pane:first").addClass("active");
    });
  </script>
<?php } ?>
