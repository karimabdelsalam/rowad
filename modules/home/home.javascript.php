<script type="text/javascript">
  $(document).ready(function () {

    if (typeof Dropzone != 'undefined'){
      Dropzone.prototype.defaultOptions.dictDefaultMessage = jslang.dropzone.dictDefaultMessage;
      Dropzone.prototype.defaultOptions.dictFallbackMessage = jslang.dropzone.dictFallbackMessage;
      Dropzone.prototype.defaultOptions.dictFallbackText =jslang.dropzone.dictFallbackText;
      Dropzone.prototype.defaultOptions.dictFileTooBig = jslang.dropzone.dictFileTooBig;
      Dropzone.prototype.defaultOptions.dictInvalidFileType = jslang.dropzone.dictInvalidFileType;
      Dropzone.prototype.defaultOptions.dictResponseError = jslang.dropzone.dictResponseError;
      Dropzone.prototype.defaultOptions.dictCancelUpload = jslang.dropzone.dictCancelUpload;
      Dropzone.prototype.defaultOptions.dictCancelUploadConfirmation = jslang.dropzone.dictCancelUploadConfirmation;
      Dropzone.prototype.defaultOptions.dictRemoveFile = jslang.dropzone.dictRemoveFile;
      Dropzone.prototype.defaultOptions.dictMaxFilesExceeded = jslang.dropzone.dictMaxFilesExceeded;
    }

    if (typeof Dropzone != 'undefined')
      Dropzone.autoDiscover = false;
    if (typeof Dropzone != 'undefined') {
      var myDropzone = new Dropzone(".dropzone", {"addRemoveLinks": true});
      <?php if(!empty($params['mediafiles'])){
      echo '$(".dz-message").hide();';
      foreach($params['mediafiles'] as $mediafile){
        echo 'var mockFile = {name: "' . $mediafile['name'] . '", url: "' . $mediafile['path'] . '", size: "' . $mediafile['size'] . '"};
        myDropzone.options.addedfile.call(myDropzone, mockFile);';
        if(preg_match('/.(jpg|png|gif|jpeg|jpe)$/i', $mediafile['path'])) echo 'myDropzone.options.thumbnail.call(myDropzone, mockFile, "' . $mediafile['path'] . '");';
      }
    }?>
      myDropzone.on("success", function (file, response) {
        file.previewElement.addEventListener("click", function () {
          displayDownloadBox(response);
        });
      });
      myDropzone.on("removedfile", function (file) {
        $(".smartiocp-loading").show();
        $.get("<?php echo CPURL;?>/<?php echo Module;?>/removefile/", {
          "no_header": 1,
          "file": file.name
        }, function (data) {
          $(".smartiocp-loading").hide();
        });
      });
      $(".dz-details").click(function () {
        var filename = $(this).find("span[data-dz-url]").text();
        displayDownloadBox(filename);
      });
    }

    $("input[name='income_tax_status']").change(function () {
      if($(this).val() == 1)
        $('.incomeTaxField').removeClass('hide2');
      else
        $('.incomeTaxField').addClass('hide2');
    });
    $("input[name='income_tax_status']").trigger("change");

    $("input[name='owner_tax_status']").change(function () {
      if($(this).val() == 1)
        $('.incomeOwnerTaxField').removeClass('hide2');
      else
        $('.incomeOwnerTaxField').addClass('hide2');
    });
    $("input[name='owner_tax_status']").trigger("change");

    $("input[name='habit_tax_status']").change(function () {
      if($(this).val() == 1)
        $('.habitTaxField').removeClass('hide2');
      else
        $('.habitTaxField').addClass('hide2');
    });
    $("input[name='habit_tax_status']").trigger("change");

    $("input[name='comm_tax_status']").change(function () {
      if($(this).val() == 1)
        $('.commTaxField').removeClass('hide2');
      else
        $('.commTaxField').addClass('hide2');
    });
    $("input[name='comm_tax_status']").trigger("change");

    function displayDownloadBox(fileurl) {
      $(".open-ajax-body").html('<div class="innerAll inner-2x"><div class="form-group"><label class="col-md-3 control-label"><?php echo gettext('رابط الملف') ?></label><div class="col-md-9"><input type="text" class="form-control" onclick="this.focus();this.select();" value="' + fileurl + '" /></div></div></div>');
      $('#open-ajax-form').modal();
    }

    $('.getSMSBalance').click(function (e) {
      var element = this;
      $(this).html('<i class="fa fa-plug"></i> <?php echo gettext('يتم الاتصال') ?>');
      $(".smartiocp-loading").show();
      $.get("<?php echo CPURL;?>/<?php echo Module;?>/smsbalance/", {
        "no_header": 1
      }, function (data) {
        $(".smartiocp-loading").hide();
        $(element).html(data);
      });
    });

    <?php if(Action == 'owners'):?>
    $("#ownerAjaxAdder").click(function () {
      $(".ownerAjaxHolder").append($(".ownerAjaxTemplate").clone());
      $(".ownerAjaxHolder .ownerAjaxTemplate:last").addClass("ownerAjaxCloned").removeClass("ownerAjaxTemplate");
      $(".ownerAjaxHolder .ownerAjaxCloned:last").find(".ajaxSelectFreezed").removeClass("ajaxSelectFreezed").addClass("ajaxSelect");
      pageAutoLoad(".ownerAjaxHolder .ownerAjaxCloned:last");
      $(".ownerAjaxHolder .ownerAjaxCloned:last").removeClass("hide2");
      sortOwners();
    });
    if ($("#ownerAjaxAdder").length > 0 && $(".ownerAjaxCloned").length == 0) {
      $("#ownerAjaxAdder").trigger("click");
    }
    else {
      sortOwners();
    }
    <?php endif;?>

    if ($("select[name='sms_provider']").length > 0) {
      $("select[name='sms_provider']").change(function () {
        $(".smsProvider_settings").show();
        if ($(this).val() == "") {
          $(".smsProvider_link").attr("href", "javascript:");
          $(".smsProvider_settings").hide();
        }
        else if ($(this).val() == "clickatell") {
          $(".smsProvider_link").attr("href", "https://www.clickatell.com");
          $(".smsProvider_key label").html("API Key");
          $(".smsProvider_secret").hide();
        }
        else if ($(this).val() == "smsglobal") {
          $(".smsProvider_link").attr("href", "https://smsglobal.com");
          $(".smsProvider_key label").html("Username");
          $(".smsProvider_secret label").html("Password");
          $(".smsProvider_secret").show();
        }
        else if ($(this).val() == "twilio") {
          $(".smsProvider_link").attr("href", "https://www.twilio.com");
          $(".smsProvider_key label").html("ACCOUNT SID");
          $(".smsProvider_secret label").html("AUTH TOKEN");
          $(".smsProvider_secret").show();
        }
        else if ($(this).val() == "bulksms") {
          $(".smsProvider_link").attr("href", "https://www.bulksms.com");
          $(".smsProvider_key label").html("Username");
          $(".smsProvider_secret label").html("Password");
          $(".smsProvider_secret").show();
        }
        else if ($(this).val() == "smsapi") {
          $(".smsProvider_link").attr("href", "https://www.smsapi.com");
          $(".smsProvider_key label").html("TOKEN");
          $(".smsProvider_secret").hide();
        }
        else if ($(this).val() == "msg91") {
          $(".smsProvider_link").attr("href", "https://www.msg91.com");
          $(".smsProvider_key label").html("Authkey");
          $(".smsProvider_secret").hide();
        }
        else if ($(this).val() == "unifonic") {
          $(".smsProvider_link").attr("href", "https://www.unifonic.com");
          $(".smsProvider_key label").html("AppSid");
          $(".smsProvider_secret").hide();
        }
        else if ($(this).val() == "nexmo") {
          $(".smsProvider_link").attr("href", "https://www.nexmo.com");
          $(".smsProvider_key label").html("Key");
          $(".smsProvider_secret label").html("Secret");
          $(".smsProvider_secret").show();
        }
        else if ($(this).val() == "clicksend") {
          $(".smsProvider_link").attr("href", "https://www.clicksend.com");
          $(".smsProvider_key label").html("Username");
          $(".smsProvider_secret label").html("API Key");
          $(".smsProvider_secret").show();
        }
        else if ($(this).val() == "mobilysms") {
          $(".smsProvider_link").attr("href", "https://www.mobilysms.net");
          $(".smsProvider_key label").html("<?php echo gettext('اسم المستخدم') ?>");
          $(".smsProvider_secret label").html("<?php echo gettext('كلمة المرور') ?>");
          $(".smsProvider_secret").show();
        }
      });
      $("select[name='sms_provider']").trigger("change");
    }

  });

  <?php if(Action == 'owners'):?>
  function verifyOwnerShares() {
    var totalshares = 0;
    $(".ownerAjaxCloned input[name*='share[']").each(function () {
      var share = parseInt($(this).val());
      totalshares = totalshares + share;
    });
    if (totalshares != 100) {
      alert("<?php echo gettext('خطأ! مجموع الحصص للملاك لا يساوي 100') ?>");
      return false;
    }
    return true;
  }

  function sortOwners() {
    var loop = 0;
    $(".ownerAjaxCloned").each(function (index) {
      loop = index;
      $(this).find("span.ownerSort").html((index + 1));
      $(this).find("input, select, textarea").each(function (index) {
        var name = $(this).attr("name");
        name = name.replace("[]", "[" + loop + "]");
        $(this).attr("name", name);
      });
    });
  }
  <?php endif;?>

</script>
