(function (a) {
  a.switcher = function (c) {
    var b = a("input[type=checkbox],input[type=radio]");
    if (c !== undefined && c.length) {
      b = b.filter(c)
    }
    b.each(function () {
      var e = a(this).hide();
      e.attr("checked", "checked");
      var d = a(document.createElement("div")).addClass("ui-switcher").attr("aria-checked", (e.val() == 1));
      if ("radio" === e.attr("type")) {
        d.attr("data-name", e.attr("name"))
      }
      toggleSwitch = function (f) {
        if (e.is('[readonly]')){
          return;
        }
        if (f.target.type === undefined) {
          //e.trigger(f.type)
        }
        if ("radio" === e.attr("type")) {
          a(".ui-switcher[data-name=" + e.attr("name") + "]").not(d.get(0)).attr("aria-checked", false);
        }
        if(e.val() == 1){
          e.val(0);
        }
        else{
          e.val(1);
        }
        d.attr("aria-checked", (e.val() == 1));
        e.attr("checked", "checked");
        e.trigger("change");
      };
      d.on("click", toggleSwitch);
      d.insertBefore(e)
    })
  }
})(jQuery);
