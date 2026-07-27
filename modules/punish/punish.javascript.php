<script type="text/javascript">
  $(document).ready(function () {

    $("select[name='reason']").change(function () {
      if ($(this).val() == 4) {
        $(".otherReasonField").show();
      }
      else {
        $(".otherReasonField").hide();
      }
    });
    $("select[name='reason']").trigger("change");

    $(".employeeAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن الموظف بالاسم او رقم الهوية') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/employees/search/",
        dataType: 'json',
        results: function (data) {
          return {results: data};
        },
        data: function (params) {
          return {
            q: params.term,
            callpage: params.page || 1
          }
        }
      }
    });

  });
</script>
