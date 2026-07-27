var primaryColor = '#3695d5',
  dangerColor = '#b55151',
  successColor = '#609450',
  infoColor = '#4a8bc2',
  warningColor = '#ab7a4b',
  inverseColor = '#45484d';
var themerPrimaryColor = primaryColor;
var ELMcounter = 0;
var quickFormClosed = 1;

$.validator.setDefaults({
  showErrors: function (map, list) {
    $('.tooltip').remove();
    this.currentElements.parents('label:first, div:first').find('.has-error').remove();
    this.currentElements.parents('.form-group:first').removeClass('has-error');
    this.currentElements.parents('.form-group:first').find('span.select2-container').removeAttr("data-toggle");
    this.currentElements.parents('.form-group:first').find('span.select2-container').removeAttr("data-placement");
    this.currentElements.parents('.form-group:first').find('span.select2-container').removeAttr("title");
    this.currentElements.removeAttr("data-toggle");
    this.currentElements.removeAttr("data-placement");
    this.currentElements.removeAttr("data-original-title");
    this.currentElements.removeAttr("title");
    $.each(list, function (index, error) {
      var ee = $(error.element);
      var eep = ee.parents('label:first').length ? ee.parents('label:first') : ee.parents('div:first');
      ee.parents('.form-group:first').addClass('has-error');

      eep.find('span.select2-container').attr("data-toggle", "tooltip");
      eep.find('span.select2-container').attr("data-placement", "left");
      eep.find('span.select2-container').attr("title", error.message);

      ee.attr("data-toggle", "tooltip");
      ee.attr("data-placement", "left");
      ee.attr("title", error.message);
    });
    $('[data-toggle="tooltip"]').tooltip('destroy');
    $('body').tooltip({selector: '[data-toggle="tooltip"]', html: true});
  }
});

var ajaxformoptions = {
  beforeSubmit: function (arr, form, options) {
    $(".smartiocp-loading").show();
    $(form).find("button[type='submit']").attr("data-loading-text", jslang.confirm_loading_text);
    $(form).find("button[type='submit']").button('loading');
  },
  success: function (response, statusText, xhr, form) {
    $(".smartiocp-loading").hide();
    $(form).find("button[type='submit']").button('reset');
    var jsonresponse = "";
    try {
      jsonresponse = JSON.parse(response);
    } catch (e) {
      console.log(response);
    }
    if (typeof jsonresponse == "object") {
      if (jsonresponse["status"] == 0) {
        $(form).find(".alert").remove();
        $(form).prepend('<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">×</button>' + jsonresponse["message"] + '</div>');
        $('body, html').animate({scrollTop: $(form).offset().top - 50}, 'slow');
        return;
      }
      else if (jsonresponse["status"] == 1) {
        window.location = jsonresponse["message"];
      }
      else if (jsonresponse["status"] == "close") {
        $('#open-ajax-form').modal("hide");
      }
      else if (jsonresponse["status"] == "close-quick-form") {
        var quickFormResponse = "";
        try {
          quickFormResponse = JSON.parse(jsonresponse["message"]);
        } catch (e) {
        }
        if (typeof quickFormResponse == "object") {
          if ($('#' + quickFormResponse["element"]).hasClass("noSelect2")) {
            $('#' + quickFormResponse["element"]).append($('<option></option>').val(quickFormResponse["id"]).html(quickFormResponse["title"])).val(quickFormResponse["id"]);
            $('#' + quickFormResponse["element"]).trigger("change");
          }
          else if (! $('#' + quickFormResponse["element"]).hasClass("multiSelect")) {
            $('#' + quickFormResponse["element"]).append($('<option></option>').val(quickFormResponse["id"]).html(quickFormResponse["title"])).val(quickFormResponse["id"]);
            $('#' + quickFormResponse["element"]).select2("destroy");
            $('#' + quickFormResponse["element"]).select2();
          }
        }
        if(quickFormClosed === 1){
          $('#open-quick-form-spare').modal("hide");
          $('#open-quick-form-spare .open-quick-body').html("");
        } else {
          $('#open-quick-form').modal("hide");
          $('#open-quick-form .open-quick-body').html("");
        }
        if (jsonresponse.parameter && jsonresponse.parameter.function) {
          window[jsonresponse.parameter.function](jsonresponse.parameter.param);
        }
      }
      else if (jsonresponse["status"] == "html") {
        $('#output-ajax-form').html(jsonresponse["message"]);
      }
      else if (jsonresponse["status"] === "callback") {
        window[jsonresponse["message"]](jsonresponse["parameter"]);
      }
      else if (jsonresponse["status"] == "wizard") {
        var wizardIDs = jsonresponse["message"].split("->");
        $(wizardIDs[0]).addClass("hide2");
        $(wizardIDs[1]).removeClass("hide2");
      }
      else if (jsonresponse["status"] == "openAjax") {
        $(form).append("<a href='" + jsonresponse["message"] + "' class='open-ajax form-auto-open-ajax'></a>");
        pageAutoLoad(form, true);
        $(form).find("a.form-auto-open-ajax").trigger("click");
      }
      else if (jsonresponse["status"] == "success") {
        $(form).html("");
        $(form).find(".alert").remove();
        $(form).prepend('<div class="alert alert-success">' + jsonresponse["message"] + '</div>');
        return;
      }
      else if (jsonresponse["status"] == "uploadedfile") {
        $('#open-ajax-form').modal("hide");
        if($('#open-quick-form').hasClass('in')){
          $("#open-quick-form .file-uploaded-zone").append(jsonresponse["message"]);
          pageAutoLoad("#open-quick-form .file-uploaded-zone li:last", true);
        } else {
          $("form.form-ajax .file-uploaded-zone").append(jsonresponse["message"]);
          pageAutoLoad("form.form-ajax .file-uploaded-zone li:last", true);
        }
        return;
      }
      else {
        $(jsonresponse["status"]).prepend(jsonresponse["message"]);
        $('#open-ajax-form').modal("hide");
      }
    }
  }
};

pageAutoLoad("body", true);

$(document).ready(function () {

  $('.nav-tabs a').click(function(){
    setTimeout(function (){
      if($('.nav-tabs > .active').index()+1 === $('.nav-tabs li').length){
        $("#btn-tabs-next").hide();
      } else {
        $("#btn-tabs-next").show();
      }
    }, 200)
  });
  $('#btn-tabs-next').click(function(){
    if($('.nav-tabs > .active').index()+2 === $('.nav-tabs li').length){
      $("#btn-tabs-next").hide();
    }
    $('.nav-tabs > .active').next('li').find('a').trigger('click');
  });

  $('#btn-tabs-prev').click(function(){
    $('.nav-tabs > .active').prev('li').find('a').trigger('click');
  });

  if ($('.ajax-login-form-btn').length > 0) {
    setTimeout(function () {
      checkLoginStatus();
    }, 30000);
  }

  $("#printOTF").click(function (e) {
    var pageLink = window.location.href;
    if(pageLink.indexOf("?") >= 0){
      pageLink += "&printotf=all";
    } else {
      pageLink += "?printotf=all";
    }
    var win = window.open(pageLink, '_blank');
    win.focus();
  });

  $("#exportOTF").click(function (e) {
    var pageLink = window.location.href;
    if(pageLink.indexOf("?") >= 0){
      pageLink += "&exportotf=excel";
    } else {
      pageLink += "?exportotf=excel";
    }
    var win = window.open(pageLink, '_blank');
    win.focus();
  });

  if ($('li.activeMenu').length > 0) {
    $('#menu ul.menu li').has("li.activeMenu").find("a").eq(0).trigger("click");
    $('#menu ul.menu li ul li').has("ul li.activeSubMenu").find("a").eq(0).trigger("click");
  }

  $('.widget-body-white table.table').each(function () {
    if ($(this).width() < 1200 && $(this).width() > 800) {
      $(this).find('td.auto-hide').attr("style", "max-width: 100px;overflow: hidden");
    }
  });

});

function checkLoginStatus() {
  setTimeout(function () {
    checkLoginStatus();
  }, 60000);
  if ($('.login-quick-form').length > 0) {
    return;
  }
  $.ajax({
    url: CPURL + "/dashboard/myaccount",
    type: 'get',
    data: {"no_header": 1}
  }).error(function (xhr, status) {
    if (xhr.status === 401) {
      $(".ajax-login-form-btn").trigger("click");
    }
  });
}

function pageAutoLoad(element, isFreshContent) {
  var uniqueID, freshContents;

  if(element != "body"){
      $(element).find("input, select, textarea").each(function () {
        if(! $(this).attr("name")){
          uniqueID = "noname";
        } else {
          uniqueID = $(this).attr("name").replace(/\[/g, "_").replace(/\]/g, "_");
        }
        $(this).attr("id", uniqueID + "_" + ELMcounter);
        ELMcounter++;
      });
  } else {
    $("body").find("form").each(function () {
      $(this).find("input, select, textarea").each(function () {
        if (typeof($(this).attr("id")) == "undefined" || $(this).attr("id") == "") {
          if(! $(this).attr("name")){
            uniqueID = "noname";
          } else {
            uniqueID = $(this).attr("name").replace(/\[/g, "_").replace(/\]/g, "_");
          }
          $(this).attr("id", uniqueID + "_" + ELMcounter);
          ELMcounter++;
        }
      });
    });
  }

  if(typeof isFreshContent === "undefined"){
    freshContents = false;
  } else {
    freshContents = isFreshContent;
  }

  $(element).find("form").each(function () {
    if ($(this).hasClass("form-ajax")) {
      $(this).prepend('<input type="hidden" name="no_header" value="1" />');
    }
    $(this).validate();
  });

  $(element).find(".form-ajax").ajaxForm(ajaxformoptions);

  if ($(element).find('#smsCounter').length > 0) {
    $(element).find('#smsCounter').countSms('#smsCounterStats');
  }

  if ($(element).find(".openQHtml").length > 0) {
    $(element).find(".openQHtml").fancybox({
      maxWidth: 800,
      maxHeight: 600,
      fitToView: false,
      width: '70%',
      height: '70%',
      autoSize: false,
      closeClick: false,
      openEffect: 'none',
      closeEffect: 'none'
    });
  }

  $(element).find(".moreSearchOptions").click(function () {
    if ($(this).find("i").hasClass("fa-sort-desc")) {
      $(this).find("i").removeClass("fa-sort-desc").addClass("fa-sort-asc");
      $(this).closest("form").find("div.form-group").removeClass("hide2");
    }
    else {
      $(this).find("i").removeClass("fa-sort-asc").addClass("fa-sort-desc");
      $(this).closest("form").find("div.form-group").addClass("hide2");
      $(this).closest("form").find("div.form-group").eq(0).removeClass("hide2");
    }
  });

  $(element).find(".checkMultiBoxes").click(function (e) {
    if ($("body input:checked").size() < 1) {
      e.preventDefault();
      e.stopImmediatePropagation();
      alert(jslang.no_element_selected);
    }
  });

  $(element).find('.quick-form').click(function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    var href = $(this).attr('href');
    var elmID = $(this).closest('div.form-group').find("select").attr("id");
    $(".smartiocp-loading").show();
    $.get(href, {"quick_form": 1, "no_header": 1}, function (data) {
      $(".smartiocp-loading").hide();

      var jsonresponse = "";
      try {
        jsonresponse = JSON.parse(data);
      } catch (e) {}

      if (typeof jsonresponse == "object") {
        if (jsonresponse["status"] === "callback") {
          window[jsonresponse["message"]](jsonresponse["parameter"]);
        } else if(jsonresponse["status"] === 0) {
          alert(jsonresponse.message);
          return;
        }
      }

      if(quickFormClosed === 1){
        quickFormClosed = 0;
        var targetForm = "#open-quick-form";
        $('#open-quick-form').on('hidden.bs.modal', function (e) {
          quickFormClosed = 1;
        });
        $(".open-quick-body").html(data);
      } else {
        var targetForm = "#open-quick-form-spare";
        $(".open-quick-body-spare").html(data);
        $("#open-quick-form").modal("hide");
        $('#open-quick-form-spare').on('hidden.bs.modal', function (e) {
          quickFormClosed = 0;
          $("#open-quick-form").modal("show");
        });
      }
      $(targetForm + " form").attr("action", href);
      $(targetForm + " form").append('<input name="elementID" type="hidden" value="' + elmID + '" />');
      $(targetForm + " form").append('<input name="quick_form" type="hidden" value="1" />');
      $(targetForm).modal();
      pageAutoLoad(targetForm, true);
    });
  });

  $(element).find('.open-ajax').click(function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    var href = $(this).attr('href');
    var title = $(this).attr('title');
    $(".smartiocp-loading").show();
    $('#open-ajax-form .modal-title').text(title);
    $('.open-ajax-body').html("");
    $.get(href, {
      "no_header": 1
    }, function (data) {
      $(".smartiocp-loading").hide();
      $(".open-ajax-body").html(data);
      $('#open-ajax-form').modal();
      pageAutoLoad("#open-ajax-form", true);
    });
  });

  $(element).find('.go-back').click(function () {
    window.history.back();
  });

  $(element).find('.checkAll').click(function () {
    if ($(this).is(":checked")) {
      $(this).closest("table").find("tbody input[type=checkbox]").attr("checked", "checked");
      $(this).closest("table").find("tbody tr").addClass("selected");
    }
    else {
      $(this).closest("table").find("tbody input[type=checkbox]").removeAttr("checked");
      $(this).closest("table").find("tbody tr").removeClass("selected");
    }
  });
  $(element).find('tr.selectable').click(function () {
    if ($(this).closest("tr").hasClass("selected")) {
      $(this).find("input[type=checkbox]").removeAttr("checked");
      $(this).closest("tr").removeClass("selected");
    }
    else {
      $(this).find("input[type=checkbox]").attr("checked", "checked");
      $(this).closest("tr").addClass("selected");
    }
  });

  $(element).find('.delete-ajax').click(function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    if (confirm(jslang.confirm_delete_msg)) {
      var href = $(this).attr("href");
      var parentElm = $(this).attr("data-parent");
      if (typeof parentElm == "undefined") {
        parentElm = "tr";
      }
      var element = this;
      $(".smartiocp-loading").show();
      $.get(href, {
        "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        var jsonresponse = "";
        try {
          jsonresponse = JSON.parse(data);
        } catch (e) {
        }
        if (typeof jsonresponse == "object") {
          if (jsonresponse["status"] === "callback") {
            window[jsonresponse["message"]](jsonresponse["parameter"]);
          } else {
            alert(jsonresponse["message"]);
          }
          return;
        }
        $(element).closest(parentElm).fadeOut("slow", function () {
          $(element).closest(parentElm).remove();
        });
      });
    }
  });

  $(element).find("h4[data-collapse]").click(function () {
    $("." + $(this).attr("data-collapse")).slideToggle();
    $(this).find("i").toggleClass("fa-minus-square-o");
    $(this).find("i").toggleClass("fa-plus-square-o");
  });

  $(element).find('.do-ajax').click(function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    if ($(this).hasClass("with-confirm") && !confirm(jslang.confirm_delete_msg)) {
      return false;
    }
    var hideAfter = false;
    if ($(this).hasClass("with-hide")) {
      hideAfter = true;
    }
    var href = $(this).attr('href');
    var action = $(this).attr('data-action');
    var element = this;
    $(".smartiocp-loading").show();
    $.get(href, {
      "no_header": 1
    }, function (data) {
      $(".smartiocp-loading").hide();
      var jsonresponse = "";
      try {
        jsonresponse = JSON.parse(data);
      } catch (e) {
      }
      if (typeof jsonresponse == "object") {
        if (jsonresponse["status"] === "callback") {
          var fn = window[jsonresponse["message"]];
          if (typeof fn === "function") fn.apply(null, [jsonresponse["parameter"]]);
        } else {
          alert(jsonresponse["message"]);
          return;
        }
      }
      if (hideAfter) {
        $(element).closest("tr").fadeOut("slow", function () {
          $(element).remove()
        });
      }
      else if (typeof action == "undefined") {
        $(element).closest("tr").hide().fadeIn("slow");
      }
      else {
        $(element).attr("disabled", "disabled");
      }
    });
  });

  $(element).find('.remove-dim').click(function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    $(".contract-dim").removeClass("contract-dim");
    $("input[name='dimremoved']").val(1);
  });

  $(element).find('.sendSMS').click(function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    var number = $("#smsNumber").val();
    if (number === "") {
      alert(jslang.mobile_is_required);
      return;
    }
    var href = $(this).attr('href');
    var successMSG = $(this).attr('data-success');
    var element = this;
    var oldText = $(this).html();
    $(this).html($(element).attr("data-load"));
    $(".smartiocp-loading").show();
    $(element).attr("disabled", "disabled");
    $.get(href, {
      "number": number, "no_header": 1
    }, function (data) {
      $(".smartiocp-loading").hide();
      if (successMSG !== "") {
        $(element).html(successMSG);
      }
      else {
        $(element).html(oldText);
      }
      $(element).attr("disabled", "disabled");
    });
  });

  $(element).find('.instantReq').click(function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    var href = $(this).attr('href');
    var successMSG = $(this).attr('data-success');
    var element = this;
    var oldText = $(this).html();
    $(this).html($(element).attr("data-load"));
    $(".smartiocp-loading").show();
    $(element).attr("disabled", "disabled");
    $.get(href, {
      "no_header": 1
    }, function (data) {
      $(".smartiocp-loading").hide();
      data = JSON.parse(data);
      if(data.status === 0){
        $(element).removeClass("btn-success").addClass("btn-danger");
        $(element).html(data.message);
      } else {
        if (successMSG !== "") {
          $(element).html(successMSG);
        }
        else {
          $(element).html(oldText);
        }
      }
      $(element).attr("disabled", "disabled");
    });
  });

  if ($(element).find('.jradio').length > 0) {
    $(element).find(".jradio").labelauty();
  }

  if ($(element).find('#actionGroupMenu select option').size() > 1) {
    $(element).find("#actionGroupMenu").removeClass("hide2");
  } else {
    $(element).find("#actionGroupMenu").removeClass("inline-block");
  }

  $.switcher(element+" .ckeckonoff");

  $(element).find(".selcAll").click(function () {
    $(this).focus().select();
  });

  $(element).find('.multiuploadBtn').click(function () {
    var inputname = $(this).closest("div").find("input:file").attr("name");
    $(this).closest("div.form-group").find("div").has("input:file").append('<input class="form-control" name="' + inputname + '" type="file" />');
  });

  if ($(element).find("textarea.ajaxeditor").length)
    $(element).find("textarea.ajaxeditor").ckeditor({"language": LANG_CODE, "autoParagraph": false, "fillEmptyBlocks": false, "tabSpaces": 0, "height": "600px", "extraPlugins": "zoom"});

  if ($(element).find("textarea.pagebuilder").length)
    $(element).find("textarea.pagebuilder").ckeditor({"language": LANG_CODE, "autoParagraph": false, "fillEmptyBlocks": false, "tabSpaces": 0, "height": "1200px", "allowedContent": true, "extraPlugins": "zoom"});

  if ($(element).find(".input-group").length) {
    $(".input-group").each(function( index ) {
      if($(this).find(".input-group-addon").length == 0 && $(this).find(".input-group-btn").length == 0){
        $(this).removeClass("input-group");
      }
    });
  }

  if ($(element).find("select:not(.multiSelect,.noSelect2)").length) {
    if(! freshContents){
      $(element).find("span.select2").remove();
    }
    $(element).find("select.fit-content").attr("style", "width:fit-content");
    $(element).find("select:not(.fit-content,.ajaxSelect,.multiSelect,.noSelect2)").attr("style", "width:100%");
    var $select = $(element).find("select:not(.multiSelect,.noSelect2)").select2();
    $select.on('change', function () {
      $(this).trigger('blur');
    });
  }
  if ($(element).find(".multiSelect").length) {
    $(element).find(".multiSelect").select2({tags: true});
  }

  if ($(element).find(".timepicker").length) {
    $(element).find('.timepicker').timepicker();
  }
  if ($(element).find(".datetimepicker").length) {
    $(element).find('.datetimepicker').daterangepicker({
      "autoUpdateInput": true,
      "timePicker": true,
      "singleDatePicker": true,
      "showDropdowns": true,
      "autoApply": false,
      "locale": date_picker_local
    });
  }

  if ($(element).find(".monthpicker").length) {
    $(element).find('.monthpicker').daterangepicker({
      "autoUpdateInput": false,
      "timePicker": false,
      "singleDatePicker": true,
      "showDropdowns": true,
      "autoApply": false,
      "locale": date_picker_local
    });
    $(element).find('.monthpicker').on('apply.daterangepicker', function (ev, picker) {
      $(this).val(picker.startDate.format('YYYY-MM'));
      $(this).trigger("change");
    });
  }

  if ($(element).find('.daterange').length > 0) {
    $(element).find('.daterange').daterangepicker({
      "alwaysShowCalendars": true,
      autoUpdateInput: true,
      "ranges": {
        'Today': [moment(), moment()],
        'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
        'Last 7 Days': [moment().subtract(6, 'days'), moment()],
        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
        'This Month': [moment().startOf('month'), moment().endOf('month')],
        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
      },
      "locale": date_picker_local
    });
  }

  if ($(element).find('.ardate').length > 0) {
    $(element).find(".ardate").removeClass("is-calendarsPicker");
    dateUmPicker($(element).find('.ardate'));
  }
  if ($(element).find(".datepicker").length) {
    dateGuPicker($(element).find('.datepicker'));
  }

  $(element).find(".calendarAutoChange").change(function () {
    var parent = $(this).closest("form");
    if ($(this).val() == 1) {
      $(parent).find('.ardate').calendarsPicker('destroy');
      $(parent).find('.ardate').each(function () {
        $(this).removeClass("ardate").addClass("datepicker");
        if ($(this).val() != "" && $(this).val().indexOf("/") > 0) {
          var cdate = moment($(this).val(), 'iYYYY/iM/iD');
          $(this).val(cdate.format('YYYY-M-D'));
          $(this).trigger("change");
        }
        dateGuPicker($(this));
      });
    }
    else if ($(this).val() == 2) {
      $(parent).find('.datepicker').each(function () {
        $(this).data('daterangepicker').remove();
        $(this).removeClass("datepicker").removeClass("is-calendarsPicker").addClass("ardate");
        if ($(this).val() != "" && $(this).val().indexOf("-") > 0) {
          var cdate = moment($(this).val(), 'YYYY-M-D');
          $(this).val(cdate.format('iYYYY/iM/iD'));
          $(this).trigger("change");
        }
        dateUmPicker($(this));
      });
    }
  });
  if ($(element).find('.calendarAutoChange').length > 0) {
    $(element).find('.calendarAutoChange').trigger("change");
  }

  if ($(element).find('table.table-striped').length > 0) {
    $(element).find('table.table-striped').each(function (i) {
      i++;
      var className = 'jrt-instance-' + i;
      var $this = $(this);
      $this.addClass('jrt');
      $this.addClass(className);

      var respondHtml = '<style type="text/css">\n';
      respondHtml += '@media only screen and (max-width:800px)  {\n';
      var arrHeaderText = [];
      $this.find('thead th').each(function (i, $text) {
        i++;
        $text = $(this).text();
        if ($text.trim() != "") {
          arrHeaderText.push($text);
          respondHtml += '\t.' + className + '>tbody>tr>td.jrt-cell-' + i + ':before { content: "' + $text + '"; }\n';
          if(LANG_DIR === "rtl"){
            respondHtml += '\t.' + className + '>tbody>tr>td.jrt-cell-' + i + ' { padding-right: 105px!important }\n';
          } else {
            respondHtml += '\t.' + className + '>tbody>tr>td.jrt-cell-' + i + ' { padding-left: 105px!important }\n';
          }
        }
      });
      $this.find('tbody > tr').each(function (i) {
        var $this = $(this);
        i++;
        var arrColspan = [];
        var modIndex = [];
        $this.find('td').each(function (i, c, m) {
          var $this = $(this);
          i++;
          if (modIndex > 0) {
            modIndex[0];
            i++;
          }
          if (arrColspan > 0) {
            m = (i + arrColspan.shift() - 1);
            modIndex.splice(0, 1);
            modIndex.push(m);
            i = m;
          }
          if ($this.is('[colspan]')) {
            c = parseInt($(this).prop('colspan'), 10);
            arrColspan.push(c);
          }
          $this.addClass('jrt-cell-' + i);
        });
      });
      respondHtml += '}\n';
      respondHtml += '</style>';
      $this.before(respondHtml);
    });
  }

}

function dateUmPicker(element) {
  element.calendarsPicker({calendar: $.calendars.instance('ummalqura', LANG_CODE)});
}

function dateGuPicker(element) {
  element.daterangepicker({
    "autoUpdateInput": false,
    "timePicker": false,
    "singleDatePicker": true,
    "showDropdowns": true,
    "autoApply": false,
    "locale": date_picker_local
  });
  element.on('apply.daterangepicker', function (ev, picker) {
    $(this).val(picker.startDate.format('YYYY-MM-DD'));
    $(this).trigger("change");
  });
}

(function ($) {
  // not fully supported in IE8
  if ($('html').is('.ie.lt-ie9')) {
    $('.container-fluid').css('visibility', 'visible').show();
    $('.pace').hide();
    return;
  }

  Pace.once('done', function () {
    // restore visibility
    $('.container-fluid').css('visibility', 'visible').show();
    $(window).trigger('load');
  });

})(jQuery);

(function ($) {
  window.animations = true;

  window.animateElements = function () {
    // restore visibility
    $("#menu, .navbar.main, #footer").css('visibility', 'visible').show();

    // disable animations on touch devices
    if (Modernizr.touch)
      return;

    // // disable animations if browser doesn't support css transitions & 3d transforms
    if (!$('html.csstransitions.csstransforms3d').length)
      return;

    // animate sidebar
    $("#menu").addClass('animated fadeInLeft');

    // animate main navbar & footer
    $(".navbar.main").addClass('animated fadeInUp');
    $("#footer").addClass('animated fadeInUp');

    // animate tabs
    $('.widget-tabs .tab-pane').addClass('animated fadeInUp');

    // animate thumbnails
    $(".thumbnail")
      .css('visibility', 'hidden')
      .each(function (k, v) {
        var t = $(this);
        setTimeout(function () {
          t.css('visibility', 'visible').addClass('animated fadeInDown');
        }, 200 * k);
      });

    // animate thumbnails
    $(".thumb")
      .filter(function (index) {
        return !$(this).closest('.list-group-item').length;
      })
      .css('visibility', 'hidden')
      .each(function (k, v) {
        var t = $(this);
        setTimeout(function () {
          t.css('visibility', 'visible').addClass('animated fadeInDown');
        }, 100 * k);
      });

    // animate dashboard friend list
    $(".friends-list > li")
      .css('visibility', 'hidden')
      .each(function (k, v) {
        var t = $(this);
        setTimeout(function () {
          t.css('visibility', 'visible').addClass('animated fadeInUp');
        }, 150 * k);
      });

    // animate statistical widgets
    $(".widget-stats")
      .css('visibility', 'hidden')
      .each(function (k, v) {
        var t = $(this);
        setTimeout(function () {
          t.css('visibility', 'visible').addClass('animated fadeInDown');
        }, 200 * k);
      });

    // animate generic widgets
    $(".box-generic")
      .filter(function () {
        return !$(this).parents('.timeline-activity').length;
      })
      .css('visibility', 'hidden')
      .each(function (k, v) {
        var t = $(this);
        setTimeout(function () {
          t.css('visibility', 'visible').addClass('animated fadeInUp');
        }, 250 * k);
      });
  }

  // animate only after page finished loading
  $(window).on('load', function () {
    animateElements();

    // animate page exits
    $('body')
      .on('click', 'a', function (e) {
        if (typeof $.LazyJaxDavis != 'undefined')
          return true;

        if ($(this).is('.ajaxify'))
          return true;

        if ($(this).is('[data-edit]') || $(this).is('[data-gallery]') || $(this).is('.no-ajaxify') || $(this).is('[data-toggle]') || $(this).is('[data-dismiss]') || $(this).attr('target') == '_blank')
          return true;

        if ($(this).is('.not-animated'))
          return true;

        if ($(this).parents('.bootstrap-select').length)
          return true;

        if ($(this).attr('href') == '#')
          return true;

        if ($(this).attr('href').substring(0, 11) == "javascript:")
          return true;

        e.preventDefault();
        e.stopImmediatePropagation();
        var t = $(this);

        $('body').addClass('animated fadeOutLeft');
        setTimeout(function () {
            if (t.attr('href') == '#')
              location.reload();
            else
              location = t.attr('href');
          },
          500);
      });

    // resize nicescroll areas after animations ended
    setTimeout(function () {
      resizeNiceScroll();
    }, 1000);

  });

})(jQuery);

(function ($) {

  var old = $.fn.checkbox;

  // CHECKBOX CONSTRUCTOR AND PROTOTYPE

  var Checkbox = function (element, options) {

    this.$element = $(element);
    this.options = $.extend({}, $.fn.checkbox.defaults, options);

    // cache elements
    this.$label = this.$element.parent();
    this.$icon = this.$label.find('i');
    this.$chk = this.$label.find('input[type=checkbox]');

    // set default state
    this.setState(this.$chk);

    // handle events
    this.$chk.on('change', $.proxy(this.itemchecked, this));
  };

  Checkbox.prototype = {
    constructor: Checkbox,
    setState: function ($chk) {
      $chk = $chk || this.$chk;

      var checked = $chk.is(':checked');
      var disabled = !!$chk.prop('disabled');

      // reset classes
      this.$icon.removeClass('checked disabled');

      // set state of checkbox
      if (checked === true) {
        this.$icon.addClass('checked');
      }
      if (disabled === true) {
        this.$icon.addClass('disabled');
      }
    },
    enable: function () {
      this.$chk.attr('disabled', false);
      this.$icon.removeClass('disabled');
    },
    disable: function () {
      this.$chk.attr('disabled', true);
      this.$icon.addClass('disabled');
    },
    toggle: function () {
      this.$chk.click();
    },
    itemchecked: function (e) {
      var chk = $(e.target);
      this.setState(chk);
    },
    check: function () {
      this.$chk.prop('checked', true);
      this.setState(this.$chk);
    },
    uncheck: function () {
      this.$chk.prop('checked', false);
      this.setState(this.$chk);
    },
    isChecked: function () {
      return this.$chk.is(':checked');
    }
  };


  // CHECKBOX PLUGIN DEFINITION

  $.fn.checkbox = function (option) {
    var args = Array.prototype.slice.call(arguments, 1);
    var methodReturn;

    var $set = this.each(function () {
      var $this = $(this);
      var data = $this.data('checkbox');
      var options = typeof option === 'object' && option;

      if (!data)
        $this.data('checkbox', (data = new Checkbox(this, options)));
      if (typeof option === 'string')
        methodReturn = data[option].apply(data, args);
    });

    return (methodReturn === undefined) ? $set : methodReturn;
  };

  $.fn.checkbox.defaults = {};

  $.fn.checkbox.Constructor = Checkbox;

  $.fn.checkbox.noConflict = function () {
    $.fn.checkbox = old;
    return this;
  };


  // CHECKBOX DATA-API

  $(function () {
    $(window).on('load', function () {
      //$('i.checkbox').each(function () {
      $('.checkbox-custom > input[type=checkbox]').each(function () {
        var $this = $(this);
        if ($this.data('checkbox'))
          return;
        $this.checkbox($this.data());
      });
    });
  });
})(jQuery);

(function ($) {
  if (!Modernizr.touch && $('#menu').is(':visible'))
    $('.container-fluid').removeClass('menu-hidden');

  if (Modernizr.touch)
    $('#menu').removeClass('hidden-xs');

  // handle menu toggle button action
  window.toggleMenuHidden = function () {
    if ($('.menu-right-visible').length)
      $('body').removeClass('menu-right-visible');

    $('.container-fluid').toggleClass('menu-hidden');
    $('body').toggleClass('menu-left-visible');
    $('#menu').removeClass('hidden-xs');

    if ($(window).width() <= 1024)
      $('nav ul.navbar-nav').toggleClass('hide2');

    resizeNiceScroll();
  }

  // main menu visibility toggle
  $('.navbar.main .btn-navbar, #menu .btn-navbar').click(function () {
    toggleMenuHidden();
  });


})(jQuery);

(function ($) {
  $('ul.collapse')
    .on('show.bs.collapse', function (e) {
      e.stopPropagation();

      if ($(this).closest('#menu').length) {
        var t = $(this).parents('.hasSubmenu').length;
        if (t != 1) return;

        var a = $('#menu > div > ul > li.hasSubmenu.active > ul').not(this);

        a
          .removeClass('in').addClass('collapse').removeAttr('style')
          .closest('.hasSubmenu.active').removeClass('active');
      }
    })
    .on('shown.bs.collapse', function (e) {
      e.stopPropagation();

      if ($(this).closest('#menu').length)
        $('#menu *').getNiceScroll().resize();
    });

  $('#menu_switch').on('change', function () {
    var w = $(this).parents('#menu'),
      w_fusion = w.find('#sidebar-collapse-wrapper'),
      nav = $(this).val();

    if (w_fusion.length) {
      w_fusion.find('> ul').addClass('hide');
      w_fusion.find('#' + nav).removeClass('hide');
      $('#menu *').getNiceScroll().resize();
    }
  });

  $('.btn-avatar [data-toggle="tab"]').on('show.bs.tab', function (e) {
    e.stopPropagation();
    $('.btn-avatar [data-toggle="tab"]').parent().removeClass('active');
  });

})(jQuery);

(function ($) {
  $('body')
    .on('click', '.close-discover', function (e) {
      $('#sidebar-discover-wrapper, [data-toggle="sidebar-discover"]').removeClass('open hover-closed');
      closeDiscover();
    });

  window.closeDiscover = function () {
    var discover = $('#discover'),
      target = discover.find('> div');

    if (!target.length)
      return;

    target.attr('id', target.data('id'));
    target.attr('class', target.data('class'));
    target.insertAfter('#sidebar-discover-wrapper > ul > li > a[href="#' + target.attr('id') + '"]');
  }

  window.openDiscover = function (that) {
    that = $(that);

    $('[data-toggle="sidebar-discover"]').removeClass('open');
    that.addClass('open');

    var wrapper = $('#sidebar-discover-wrapper'),
      main = wrapper.find('> ul'),
      discover = wrapper.find('> #discover'),
      target = $(that.attr('href'));

    target.data('id', target.attr('id'));
    target.data('class', target.attr('class'));
    target.removeAttr('class id');

    if (!discover.length) {
      discover = $('<div/>').attr('id', 'discover');
      wrapper.append(discover);
    }

    discover.html(target);
    wrapper.addClass('open');

    var ms = $('#menu *').getNiceScroll();
    if (ms.length)
      ms[0].doScrollTop(0);
  }

  $('#sidebar-discover-wrapper > ul > li > a').on('click', function (e) {
    closeDiscover();

    if ($(this).is('[data-toggle="sidebar-discover"]'))
      e.preventDefault();

    if ($('#sidebar-discover-wrapper.open').length) {
      e.preventDefault();
      e.stopPropagation();
    }

    if ($('.sidebar-discover-mini').length)
      $('body').removeClass('sidebar-discover-mini');

    if ($('#sidebar-discover-wrapper.open').length) {
      var that_open = $(this).is('[data-toggle="sidebar-discover"].open'),
        that = this;

      $('#sidebar-discover-wrapper, [data-toggle="sidebar-discover"]').removeClass('open hover-closed');
      closeDiscover();

      if (that_open)
        return;

      setTimeout(function () {
        openDiscover(that);
      }, 500);
      return;
    }

    openDiscover(this);
  });

})(jQuery);


(function ($, window) {

  // fix for safari back button issue
  window.onunload = function () {
  };

  $.expr[':'].scrollable = function (elem) {
    var scrollable = false,
      props = ['', '-x', '-y'],
      re = /^(?:auto|scroll)$/i,
      elem = $(elem);

    $.each(props, function (i, v) {
      return !(scrollable = scrollable || re.test(elem.css('overflow' + v)));
    });

    return scrollable;
  };

  window.beautify = function (source) {
    var output,
      opts = {};

    opts.preserve_newlines = false;
    output = html_beautify(source, opts);
    return output;
  }

  // generate a random number within a range (PHP's mt_rand JavaScript implementation)
  window.mt_rand = function (min, max) {
    var argc = arguments.length;
    if (argc === 0) {
      min = 0;
      max = 2147483647;
    }
    else if (argc === 1) {
      throw new Error('Warning: mt_rand() expects exactly 2 parameters, 1 given');
    }
    else {
      min = parseInt(min, 10);
      max = parseInt(max, 10);
    }
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  // scroll to element animation
  function scrollTo(id) {
    if ($(id).length)
      $('html,body').animate({scrollTop: $(id).offset().top}, 'slow');
  }

  window.resizeNiceScroll = function () {
    if (typeof $.fn.niceScroll == 'undefined')
      return;

    setTimeout(function () {
      $('.hasNiceScroll, #menu').getNiceScroll().show().resize();
      if ($('.container-fluid').is('.menu-hidden'))
        $('#menu').getNiceScroll().hide();
    }, 100);
  }

  // $('#content .modal').appendTo('body');

  // tooltips
  $('body').tooltip({selector: '[data-toggle="tooltip"]', html: true});

  // popovers
  $('[data-toggle="popover"]').popover();

  // print
  $('[data-toggle="print"]').click(function (e) {
    e.preventDefault();
    window.print();
  });

  // carousels
  $('.carousel').carousel();

  // Google Code Prettify
  if ($('.prettyprint').length && typeof prettyPrint != 'undefined')
    prettyPrint();

  $('[data-toggle="scrollTo"]').on('click', function (e) {
    e.preventDefault();
    scrollTo($(this).attr('href'));
  });

  $('ul.collapse')
    .on('show.bs.collapse', function (e) {
      e.stopPropagation();
      $(this).closest('li').addClass('active');
    })
    .on('hidden.bs.collapse', function (e) {
      e.stopPropagation();
      $(this).closest('li').removeClass('active');
    });

  window.enableContentNiceScroll = function (hide) {
    if ($('html').is('.ie') || Modernizr.touch)
      return;

    if (typeof $.fn.niceScroll == 'undefined')
      return;

    if (typeof hide == 'undefined')
      var hide = true;

    $('#content .col-app, .col-separator, .applyNiceScroll')
      .filter(':scrollable')
      .not('.col-unscrollable')
      .filter(function () {
        return !$(this).find('> .col-table').length;
      })
      .addClass('hasNiceScroll')
      .each(function () {
        $(this).niceScroll({
          horizrailenabled: false,
          zindex: 2,
          cursorborder: "none",
          cursorborderradius: "0",
          cursorcolor: primaryColor
        });

        if (hide == true)
          $(this).getNiceScroll().hide();
        else
          $(this).getNiceScroll().resize().show();
      });
  }

  window.disableContentNiceScroll = function () {
    $('#content .hasNiceScroll').getNiceScroll().remove();
  }

  enableContentNiceScroll();

  if ($('html').is('.ie'))
    $('html').removeClass('app');

  if (typeof $.fn.niceScroll != 'undefined') {
    $('#menu > div')
      .add('#menu_kis > div')
      .addClass('hasNiceScroll')
      .niceScroll({
        horizrailenabled: false,
        zindex: 2,
        cursorborder: "none",
        cursorborderradius: "0",
        cursorcolor: primaryColor
      }).hide();
  }

  if ($('#sidebar-discover-wrapper.mini').length)
    $('body').addClass('sidebar-discover-mini');

  if (typeof coreInit == 'undefined') {
    $('body').on('mouseenter', '.navbar.main [data-toggle="dropdown"]', function () {
      if (!$(this).parent('.dropdown').is('.open'))
        $(this).click();
    });

    $('body').on('mouseenter', '.gotomenu-dropdown', function () {
      if (!$(this).is('.open'))
        $(".gotomenu-dropdown button").click();
    });

    $('body').on('mouseleave', '.gotomenu-dropdown', function () {
      if ($(this).is('.open'))
        $(".gotomenu-dropdown button").click();
    });
  }
  else {
    $('[data-toggle="dropdown"]').dropdown();
  }

  $('.navbar.main').on('mouseleave', function () {
    $(this).find('.dropdown.open').find('> [data-toggle="dropdown"]').click();
  });

  $('[data-height]').each(function () {
    $(this).css({'height': $(this).data('height')});
  });

  $('.app [data-toggle="tab"]')
    .on('shown.bs.tab', function (e) {
      $('.hasNiceScroll').getNiceScroll().resize();
    });

  $(window).setBreakpoints({
    distinct: false,
    breakpoints: [768, 992, 1300]
  });

  $(window).bind('exitBreakpoint1300', function () {
    $('.container-fluid').addClass('menu-hidden');
  });

  $(window).bind('enterBreakpoint1300', function () {
    $('.container-fluid').removeClass('menu-hidden');
  });

  $(window).bind('exitBreakpoint992', function () {
    disableContentNiceScroll();
  });

  $(window).bind('enterBreakpoint992', function () {
    enableContentNiceScroll(false);
  });

  window.coreInit = true;

  $(window).on('load', function () {
    window.loadTriggered = true;

    if ($(window).width() < 992)
      $('.hasNiceScroll').getNiceScroll().stop();

    if (typeof animations == 'undefined')
      $('.hasNiceScroll, #menu').getNiceScroll().show().resize();

    if (typeof Holder != 'undefined') {
      Holder.add_theme("dark", {background: "#424242", foreground: "#aaa", size: 9}).run();
      Holder.add_theme("white", {background: "#fff", foreground: "#c9c9c9", size: 9}).run();
    }

    if ($('.scripts-async').length)
      $('.scripts-async .container-fluid').css('visibility', 'visible');
  });

  // weird chrome bug, sometimes the window load event isn't triggered
  setTimeout(function () {
    if (!window.loadTriggered) $(window).trigger('load');
  }, 500);

  if ($(window).width() <= 1300 && $('#menu').is(':visible')) {
    $('.container-fluid').addClass('menu-hidden');
  }

})(jQuery, window);
