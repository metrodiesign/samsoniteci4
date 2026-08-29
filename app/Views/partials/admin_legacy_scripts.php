<script src="<?= base_url('assets/bootstrap/js/bootstrap.min.js') ?>" type="text/javascript"></script>
<script src="<?= base_url('assets/dist/js/app.min.js') ?>" type="text/javascript"></script>
<script src="<?= base_url('assets/js/jquery.validate.js') ?>" type="text/javascript"></script>
<script src="<?= base_url('assets/js/validation.js') ?>" type="text/javascript"></script>
<script type="text/javascript">
        var windowURL = window.location.href;
        pageURL = windowURL.substring(0, windowURL.lastIndexOf('/'));
        var x= $('a[href="'+pageURL+'"]');
            x.addClass('active');
            x.parent().addClass('active');
        var y= $('a[href="'+windowURL+'"]');
            y.addClass('active');
            y.parent().addClass('active');
</script>
<script src="<?= base_url('assets/datatables/1.10.16/js/jquery.dataTables.min.js') ?>"></script>
<script src="<?= base_url('assets/datatables-fixedcolumns/3.2.4/js/dataTables.fixedColumns.min.js') ?>"></script>
<script>
      $(document).ready(function() {
      var table = $('#example').DataTable( {
          scrollY: "300px",
          scrollX: true,
          responsive: true,
          className: 'mdl-data-table__cell--non-numeric',
          scrollCollapse: true,
          paging: true,
          buttons:  [ 'colvis' ],
          fixedColumns: {
              leftColumns: 1,
              leftColumns: 2,
              leftColumns: 3
          }
      } );
  } );
</script>
