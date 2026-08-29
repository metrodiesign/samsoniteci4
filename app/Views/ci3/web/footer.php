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
</section>
<script src="<?php echo base_url(); ?>assets/dist/js/app.min.js" type="text/javascript"></script>
<script src="<?php echo base_url(); ?>assets/js/jquery.validate.js" type="text/javascript"></script>
<script src="<?php echo base_url(); ?>assets/js/validation.js" type="text/javascript"></script>


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
