<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-users"></i> Provider Management
          <small>Add / Edit Provider</small>
        </h1>
      </section>

      <section class="content">

          <div class="row">
               <!-- left column -->
               <div class="col-md-12">
                 <!-- general form elements -->



                  <div class="box box-primary">
                       <div class="box-header">
                           <h3 class="box-title">Enter Provider Details</h3>
                       </div><!-- /.box-header -->
                       <!-- form start -->
                       <?php $this->load->helper("form"); ?>
                       <form role="form" id="addProvider" action="<?php echo base_url() ?>addNewProvider" method="post" role="form">
                           <div class="box-body">
                               <div class="row">
                                 <div class="col-md-6">
                                   <div class="form-group">
                                       <label for="provider_name">Provider Name</label>
                                         <input type="text" class="form-control required" value="<?php echo set_value('provider_name'); ?>" id="provider_name" name="provider_name" >
                                   </div>
                                 </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="provider_tel">Provider Tel</label>
                                           <input type="text" class="form-control required" value="<?php echo set_value('provider_tel'); ?>" id="provider_tel" name="provider_tel" >
                                       </div>

                                   </div>

                               </div>

                               <div class="row">
                                   <div class="col-md-12">
                                       <div class="form-group">
                                           <label for="provider_details">Detail</label>
                                           <textarea class="form-control" rows="5" id="provider_details"  name="provider_details"></textarea>
                                       </div>
                                   </div>

                               </div>
                           </div><!-- /.box-body -->

                           <div class="box-footer">
                               <input type="submit" class="btn btn-primary" value="Submit" />
                               <input type="reset" class="btn btn-default" value="Reset" />
                           </div>
                       </form>
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
    </div>


</div>
<script src="<?php echo base_url(); ?>assets/js/addProvider.js" type="text/javascript"></script>
