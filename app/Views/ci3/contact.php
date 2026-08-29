
  <section id="contact">
    <div class="container">
      <div class="row">
        <div class="col-lg-8 con-box-wrapper">

          <div class="col-xs-12 col-lg-6 contact-wrapper" style="padding-left: 50px;">
            <div class="txt-con-service">
              <img class="ico-topic-size" src="<?php echo base_url(); ?>assets/images/img-contact-2.png" style="width: 70px;">
              <div class="txt-hm-topic">
                <div class="">CUSTOMER</div>
                <div class="">RELATION</div>
              </div>
            </div>
            <div class="ico-txt-detail">
              <div class="">EMAIL : INFO.THAILAND@SAMSONITE.COM</div>
              <div class="">OPEN : MON - FRI 9.00 AM - 5.00 PM</div>
              <div class="">TEL. 02761-9999</div>
            </div>
          </div>
        </div>

        <div class="col-lg-5 con-box-info">
          <div class="topic-txt-hm">MORE INFOMATION</div>
          <div class="topic-txt-sm">FILL YOUR INFORMATION</div>
          <?php $this->load->helper("form"); ?>
          <form role="form" id="addContact" action="<?php echo base_url() ?>contact/addContact" method="post" role="form">
            <div class="control-input">
              <input type="text" name="fullname" id="fullname" class="main-input form-control required" value="<?php echo set_value('fullname'); ?>"  placeholder="NAME & SURNAME *">
            </div>
            <div class="control-input">
              <input type="text" name="email" class="main-input form-control required email" id="email" value="<?php echo set_value('email'); ?>"  placeholder="EMAIL *">
            </div>
            <div class="control-input">
              <input type="text" name="samsoniteid" id="samsoniteid" class="main-input form-control required" value="<?php echo set_value('samsoniteid'); ?>" placeholder="SAMSONITE ID *" style="margin: 5px 10px 5px 0;">

            </div>
            <div class="control-input">
              <input type="text" name="phone" id="phone" class="main-input form-control required" value="<?php echo set_value('phone'); ?>" placeholder="PHONE NUMBER *">
            </div>
            <div class="control-input">
              <textarea type="text" name="detail" id="detail" class="main-input form-control required" rows="5" placeholder="DETAIL *"><?php echo set_value('detail'); ?></textarea>
            </div>
              <input type="submit"  class="main-btn-sm"value="SEND NOW" />
          </form>
        </div>
        <div class="col-xs-12 col-lg-6 contact-wrapper" style="border-right: 1px solid #CCCCCC;">
          <div class="txt-con-service">
            <img class="ico-topic-size" src="<?php echo base_url(); ?>assets/images/img-contact-1.png">
            <div class="txt-hm-topic">REPAIR CENTER</div>
          </div>
          <div class="ico-txt-detail">
            <div class="">3388/25-97, 51-53 SIRINRAT BLDG., 8TH FLR.,</div>
            <div class="">RAMA 4 RD., KLONG-TON, KLONG-TOEY,</div>
            <div class="">BANKGOG 10110</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
          <?php
              $this->load->helper('form');
              $error = $this->session->flashdata('error');
              if($error)
              {
          ?>
          <div class="alert alert-danger alert-dismissable">
              <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
              <?php echo $this->session->flashdata('error'); ?>
          </div>
          <?php } ?>
          <?php
              $success = $this->session->flashdata('success');
              if($success)
              {
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
    </div>
  </section>

<script src="<?php echo base_url(); ?>assets/js/addContact.js" type="text/javascript"></script>
