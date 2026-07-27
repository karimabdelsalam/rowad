<script type="text/javascript">
  $(document).ready(function () {

    $(".buyerSearch").select2({
      minimumInputLength: 0,
      placeholder: "<?php echo gettext('ابحث عن العميل بالإسم') ?>",
      ajax: {
        url: "<?php echo CPURL;?>/buyer/search/",
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
