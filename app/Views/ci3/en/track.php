<?php $this->load->helper("form"); ?>
<form role="form" id="addtrack" action="<?php echo base_url() ?>track/trackstatus" method="post" role="form">
  <section id="track">
    <!-- style="background-image: url(<?php echo base_url('assets/images/bg-tracking.png'); ?>)"  -->
    <!-- set up background cms -->
    <div class="container">
      <div class="row">
        <div class="con-center-track">
          <div class="topic-txt-hm">TRACK & TRACE</div>
          <div class="topic-txt-sm">Track Your Tracking Number</div>

          <input type="text" name="searchText" id="searchText" class="search-txt form-control required" value="<?php echo set_value('searchText'); ?>" placeholder="Your Tracking ID" style="height: 70px; text-transform: uppercase;">

          <div class="">
            <!-- <a href="" style="text-decoration: none;"> -->
            <button type="button" id="btnModal" class="main-btn-sm" data-toggle="modal" data-target="#exampleModal">HOW TO CHECK</button>
            <!-- </a> -->

            <input type="submit" class="main-btn-sm" value="CHECK NOW" />

          </div>
          <div class="mobile-only">
            <div class="btn-mobile">
              <a href="<?php echo base_url(); ?>contact">
                <div class="control-txt">
                  <i class="fa fa-envelope-o edit-ico"></i>
                  <div class="txt-sub-menu">CONTACT US</div>
                </div>
              </a>
            </div>

            <div class="btn-mobile">
              <a href="https://www.houseofsamsonite.co.th/">
                <div class="control-txt">
                  <i class="fa fa-shopping-bag edit-ico"></i>
                  <div class="txt-sub-menu">SHOPPING</div>
                </div>
              </a>
            </div>

          </div>
          <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="exampleModal" aria-hidden="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title topic-txt-hm">HOW TO CHECK</h5>
                  <!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button> -->
                </div>
                <div class="modal-body">
                  <div class="" style="text-align: left; line-height: 1; font-size: 1.2em; color: #7b7b7b;">
                    <img class="rs-bg-size" src="<?php echo base_url(); ?>assets/images/popup_en.png">
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn custom-btn" data-dismiss="modal">Close</button>
                </div>
              </div>
            </div>
          </div>
          <script type="text/javascript">
            $('#btnModal').click(function() {
              $('#myModal').modal('show');
            });
          </script>
        </div>
      </div>
      <div class="col-md-4">
        <?php
        $this->load->helper('form');
        $error = $this->session->flashdata('error');
        if ($error) {
        ?>
          <div class="alert alert-danger alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <?php echo $this->session->flashdata('error'); ?>
          </div>
        <?php } ?>
        <?php
        $success = $this->session->flashdata('success');
        if ($success) {
        ?>
          <div class="alert alert-success alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <?php echo $this->session->flashdata('success'); ?>
          </div>
        <?php } ?>

        <div class="row">
          <div class="col-md-12">
            <?php echo validation_errors('<div class="alert alert-danger alert-dismissable">', ' <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>'); ?>
          </div>
        </div>
      </div>
  </section>
</form>
<script src="<?php echo base_url(); ?>assets/js/addtrack.js" type="text/javascript"></script>