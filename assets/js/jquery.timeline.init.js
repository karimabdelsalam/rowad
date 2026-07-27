var timelineConfig = {
  scale           : "year",
  zoom            : true,
  reloadCacheKeep : false,
  endDatetime : (new Date().getFullYear() + 10) + "-01-01 00:00",
  wrapScale : false,
  firstDayOfWeek  : 6,
  minGridSize  : 80,
  width: "auto",
  height: "auto",
  bdColor: "#000",
  ruler: {
    truncateLowers: false,
    top: {
      lines:      [ "year","month" ],
      height:     26,
      fontSize:   12,
      color:      "#333",
      background: "transparent",
      locale:     (LANG_CODE == "ar") ? "ar-EG" : "en-US",
      format:     {year: "numeric", month: "long", day: "numeric"}
    },
    bottom: {
      lines:      [  ],
    }
  },
};
var timelineZoomIndex = 1;

$(document).ready(function () {
  if ($(".horzTimeline").length > 0) {
    $(".horzTimeline").Timeline(timelineConfig);
  }

  $(".timelineScaleTo").click(function () {
    var scale = $(this).attr("data-scale");
    var timelineConfig2 = {
      scale           : scale,
      ruler: {
        top: {
          lines:      (scale == "day") ? [ "year","month","day" ] : [ "year","month" ],
        }
      }
    };
    if(scale === "zoomin"){
      timelineZoomIndex--;
    }
    if(scale === "zoomout" || scale === "zoomin"){
      timelineConfig2.startDatetime = (new Date().getFullYear() - (timelineZoomIndex))+"-01-01 00:00";
      timelineConfig2.endDatetime = (new Date().getFullYear() + (timelineZoomIndex*3))+"-01-01 00:00";
      timelineConfig2.scale = "year";
    }
    if(scale === "zoomout"){
      timelineZoomIndex++;
    }
    if(timelineZoomIndex < 0){
      timelineZoomIndex = 0;
    }
    $(this).closest("div.row").find(".horzTimeline").Timeline('reload', timelineConfig2);
  });
});
