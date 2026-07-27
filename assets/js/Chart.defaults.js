Chart.defaults.global.defaultFontFamily = "Arial";
Chart.defaults.global.defaultFontStyle = "bold";
Chart.defaults.global.defaultFontSize = 13;
Chart.defaults.global.defaultFontColor = "#525252";

Chart.defaults.global.legend.display = true;
Chart.defaults.global.legend.rtl = (LANG_DIR === "rtl");
Chart.defaults.global.legend.textDirection = LANG_DIR;

Chart.defaults.global.title.display = true;
Chart.defaults.global.title.rtl = (LANG_DIR === "rtl");
Chart.defaults.global.title.textDirection = LANG_DIR;

//Chart.defaults.global.tooltips.enabled = false;
Chart.defaults.global.tooltips.rtl = (LANG_DIR === "rtl");
Chart.defaults.global.tooltips.textDirection = LANG_DIR;
Chart.defaults.global.plugins.datalabels.formatter = function (value, ctx) {
  let datasets = ctx.chart.data.datasets;
  if (datasets.indexOf(ctx.dataset) === datasets.length - 1) {
    let sum = datasets[0].data.reduce((a, b) => parseFloat(a) + parseFloat(b), 0);
    return (((parseFloat(value) / parseFloat(sum)) * 100).toFixed(1) + '%').replace(".0%","%")+"\n"+value;
  }
}
Chart.defaults.global.plugins.datalabels.anchor = "center";
Chart.defaults.global.plugins.datalabels.textAlign = "center";
Chart.defaults.global.plugins.datalabels.align = "center";
Chart.defaults.global.plugins.datalabels.font.size = 13;
Chart.defaults.global.plugins.datalabels.font.style = "normal";
Chart.defaults.global.plugins.datalabels.color = "#000";
Chart.defaults.global.plugins.datalabels.textStrokeColor = "#fff";
Chart.defaults.global.plugins.datalabels.textStrokeWidth = 3;
Chart.defaults.global.plugins.datalabels.textShadowBlur = 3;
Chart.defaults.global.plugins.datalabels.textShadowColor = "#fff";
//Chart.defaults.global.plugins.datalabels.backgroundColor = "#525252";
//Chart.defaults.global.plugins.datalabels.borderColor = "#000";
Chart.defaults.global.plugins.datalabels.rotation = 3;

//Chart.defaults.global.plugins.colorschemes.scheme = "brewer.Paired12";
