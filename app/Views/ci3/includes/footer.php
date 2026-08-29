

    <footer class="main-footer">
        <!-- <div class="pull-right hidden-xs">
          <b>Tracking</b> Admin System | Version 1.0
        </div>
        <strong>Copyright &copy; 2018 <a href="<?php echo base_url(); ?>">360innovative Co.,Ltd</a></strong> -->
        <section id="footer">
          <div class="bg-footer">
           <img class="" src="<?php echo base_url(); ?>assets/images/img-footer.png">
           <div class="txt-cen-footer">NEED HELP ? CALL OUR CUSTOMER CENTRE AT</div>
           <div class="txt-num">02-761-9999</div>
          </div>

          <!-- <div class="txt-footer-nm">The Samsonite Official Online Store accepts</div> -->
          <!-- <div class="line"></div> -->
          <!-- <div class="txt-footer-nm">Copyright &copy; 2017 Samsonite. All rights reserved.</div> -->
          <!-- <div class="txt-footer-nm">User Agreement | Privacy Policy | Personal Information Collection Statement | Sitemap</div> -->

        </section>
    </footer>

    <!-- jQuery UI 1.11.2 -->
    <!-- <script src="http://code.jquery.com/ui/1.11.2/jquery-ui.min.js" type="text/javascript"></script> -->
    <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
    <!-- Bootstrap 3.3.2 JS -->
    <script src="<?php echo base_url(); ?>assets/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>assets/dist/js/app.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>assets/js/jquery.validate.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>assets/js/validation.js" type="text/javascript"></script>
    <!-- <script src="<?php echo base_url(); ?>assets/js/multifreezer.js" type="text/javascript"></script> -->

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
    <script src="//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/fixedcolumns/3.2.4/js/dataTables.fixedColumns.min.js"></script>
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
  </body>
</html>
