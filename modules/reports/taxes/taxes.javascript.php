<script type="text/javascript">
  $(document).ready(function () {

    $(".buildingAjaxSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن العقار بالاسم') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/building/search/",
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
