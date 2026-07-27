<?php

function gettext_filter_js($string){
  echo str_replace("'", "\'", gettext($string));
}

?>
<script type="application/javascript">
  var CPURL = "<?php echo CPURL ?>", LANG_CODE = "<?php echo $_SESSION['lang']['code'] ?>", LANG_DIR = "<?php echo $_SESSION['lang']['dir'] ?>";

  if(typeof jQuery != "undefined" && typeof jQuery.validator != "undefined"){
    jQuery.extend(jQuery.validator.messages, {
      required: "<?php gettext_filter_js('هذا الحقل إلزامي') ?>",
      remote: "<?php gettext_filter_js('يرجى تصحيح هذا الحقل للمتابعة') ?>",
      email: "<?php gettext_filter_js('رجاء إدخال عنوان بريد إلكتروني صحيح') ?>",
      url: "<?php gettext_filter_js('رجاء إدخال عنوان موقع إلكتروني صحيح') ?>",
      date: "<?php gettext_filter_js('رجاء إدخال تاريخ صحيح') ?>",
      dateISO: "<?php gettext_filter_js('رجاء إدخال تاريخ صحيح (ISO)') ?>",
      number: "<?php gettext_filter_js('رجاء إدخال عدد بطريقة صحيحة') ?>",
      digits: "<?php gettext_filter_js('رجاء إدخال أرقام فقط') ?>",
      creditcard: "<?php gettext_filter_js('رجاء إدخال رقم بطاقة ائتمان صحيح') ?>",
      equalTo: "<?php gettext_filter_js('رجاء إدخال نفس القيمة') ?>",
      accept: "<?php gettext_filter_js('رجاء إدخال ملف بامتداد موافق عليه')?>",
      maxlength: jQuery.validator.format("<?php gettext_filter_js('الحد الأقصى لعدد الحروف هو {0}')?>"),
      minlength: jQuery.validator.format("<?php gettext_filter_js('الحد الأدنى لعدد الحروف هو {0}')?>"),
      rangelength: jQuery.validator.format("<?php gettext_filter_js('عدد الحروف يجب أن يكون بين {0} و {1}')?>"),
      range: jQuery.validator.format("<?php gettext_filter_js('رجاء إدخال عدد قيمته بين {0} و {1}')?>"),
      max: jQuery.validator.format("<?php gettext_filter_js('رجاء إدخال عدد أقل من أو يساوي {0}') ?>"),
      min: jQuery.validator.format("<?php gettext_filter_js('رجاء إدخال عدد أكبر من أو يساوي {0}') ?>")
    });
  }

  var jslang = {};
  jslang.confirm_delete_msg = "<?php gettext_filter_js('لا يمكن التراجع في العملية, هل تريد الأستمرار؟') ?>";
  jslang.confirm_loading_text = "<?php gettext_filter_js('جاري تنفيذ طلبك...') ?>";
  jslang.no_element_selected = "<?php gettext_filter_js('من فضلك قم بتحديد عناصر للأستمرار') ?>";
  jslang.mobile_is_required = "<?php gettext_filter_js('لم تقم بإدخال رقم الجوال') ?>";

  jslang.dropzone = {};
  jslang.dropzone.dictDefaultMessage = "<?php gettext_filter_js('لرفع ملف اسحب و افلت الملف هنا فقط') ?>";
  jslang.dropzone.dictFallbackMessage = "<?php gettext_filter_js("متصفحك لا يدعم خاصية السحب و الافلات.") ?>";
  jslang.dropzone.dictFallbackText = "<?php gettext_filter_js('من فضلك استخدم الفورم بالأسفل لرفع الملف.') ?>";
  jslang.dropzone.dictFileTooBig = "<?php gettext_filter_js('حجم الملف كبير ({{filesize}}ميجابت). اقصى حجم مسموح به : {{maxFilesize}}ميجابت.') ?>";
  jslang.dropzone.dictInvalidFileType = "<?php gettext_filter_js("لا يمكنك رفع ملفات من هذا النوع.") ?>";
  jslang.dropzone.dictResponseError = "<?php gettext_filter_js('حالة استجابة الخادم {{statusCode}} كود.') ?>";
  jslang.dropzone.dictCancelUpload = "<?php gettext_filter_js('الغاء الرفع') ?>";
  jslang.dropzone.dictCancelUploadConfirmation = "<?php gettext_filter_js('هل انت متأكد من الغاء عملية الرفع ؟') ?>";
  jslang.dropzone.dictRemoveFile = "<?php gettext_filter_js('حذف الملف') ?>";
  jslang.dropzone.dictMaxFilesExceeded = "<?php gettext_filter_js('لا يمكنك رفع مزيد من الملفات.') ?>";

  if(typeof jQuery != "undefined" && typeof jQuery.fn.select2 != "undefined"){
    (function(){if(jQuery&&jQuery.fn&&jQuery.fn.select2&&jQuery.fn.select2.amd)var e=jQuery.fn.select2.amd;return e.define("select2/i18n/auto')?>",[],function(){return{errorLoading:function(){return"<?php gettext_filter_js('لا يوجد نتائج') ?>"},inputTooLong:function(e){var t=e.input.length-e.maximum,n="<?php gettext_filter_js('من فضلك احذف') ?> "+t+" <?php gettext_filter_js('حرف') ?>";return t!=1&&(n+="s"),n},inputTooShort:function(e){var t=e.minimum-e.input.length,n="<?php gettext_filter_js('من فضلك ادخل') ?> "+t+" <?php gettext_filter_js('حرف او اكثر') ?>";return n},loadingMore:function(){return"<?php gettext_filter_js('عرض المزيد…') ?>"},maximumSelected:function(e){var t="<?php gettext_filter_js('يمكنك اختيار') ?> "+e.maximum+" <?php gettext_filter_js('عنصر') ?>";return e.maximum!=1&&(t+="s"),t},noResults:function(){return"<?php gettext_filter_js('لا يوجد نتائج') ?>"},searching:function(){return"<?php gettext_filter_js('جاري البحث...') ?>"}}}),{define:e.define,require:e.require}})();
  }

  var date_picker_local = {
    "timeformat": "YYYY-MM-DD hh:mm a')?>",
    "format": "YYYY-MM-DD')?>",
    "separator": " to ')?>",
    "applyLabel": "<?php gettext_filter_js('تنفيذ')?>",
    "cancelLabel": "<?php gettext_filter_js('إلغاء')?>",
    "fromLabel": "<?php gettext_filter_js('من')?>",
    "toLabel": "<?php gettext_filter_js('الى')?>",
    "customRangeLabel": "<?php gettext_filter_js('تخصيص')?>",
    "daysOfWeek": [
    "<?php gettext_filter_js('Su')?>",
    "<?php gettext_filter_js('Mo')?>",
    "<?php gettext_filter_js('Tu')?>",
    "<?php gettext_filter_js('We')?>",
    "<?php gettext_filter_js('Th')?>",
    "<?php gettext_filter_js('Fr')?>",
    "<?php gettext_filter_js('Sa')?>"
    ],
    "monthNames": [
    "<?php gettext_filter_js('يناير')?>",
    "<?php gettext_filter_js('فبراير')?>",
    "<?php gettext_filter_js('مارس')?>",
    "<?php gettext_filter_js('ابريل')?>",
    "<?php gettext_filter_js('مايو')?>",
    "<?php gettext_filter_js('يونيو')?>",
    "<?php gettext_filter_js('يوليو')?>",
    "<?php gettext_filter_js('اغسطس')?>",
    "<?php gettext_filter_js('سبتمبر')?>",
    "<?php gettext_filter_js('اكتوبر')?>",
    "<?php gettext_filter_js('نوفمبر')?>",
    "<?php gettext_filter_js('ديسمبر')?>"
    ],
    "firstDay": 1
  };

  var colorPlate = [
    "rgba(255, 99, 132, 0.2)", "rgba(255, 159, 64, 0.2)", "rgba(255, 205, 86, 0.2)", "rgba(75, 192, 192, 0.2)", "rgba(54, 162, 235, 0.2)", "rgba(153, 102, 255, 0.2)", "rgba(201, 203, 207, 0.2)", "rgba(217, 219, 10, 0.2)", "rgba(247, 146, 96, 0.2)", "rgba(180, 217, 152, 0.2)", "rgba(136, 244, 128, 0.2)", "rgba(210, 12, 141, 0.2)"
  ];
  var colorPlate2 = colorPlate.slice();

  function randomColor(opcity){
    if(colorPlate.length === 0){
      colorPlate = colorPlate2.slice();
    }
    var key = Math.floor(Math.random() * colorPlate.length);
    var color = colorPlate[key];
    colorPlate.splice(key, 1);
    return color.replace("0.2", opcity);
  }

</script>
