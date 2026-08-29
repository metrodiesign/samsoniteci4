<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-users"></i> Branch type Management
          <small>Add / Edit Branch Type</small>
        </h1>
      </section>

      <section class="content">

          <div class="row">
               <!-- left column -->
               <div class="col-md-12">
                 <!-- general form elements -->



                  <div class="box box-primary">
                       <div class="box-header">
                           <h3 class="box-title">Enter branch type Details</h3>
                       </div><!-- /.box-header -->
                       <!-- form start -->
                       <?php $this->load->helper("form"); ?>
                       <form role="form" id="addBranchtype" action="<?php echo base_url() ?>addNewBranchtype" method="post" role="form" enctype="multipart/form-data">
                           <div class="box-body">
                               <div class="row">

                                   <div class="col-md-12">
                                       <div class="form-group">
                                           <label for="branch_type_name">Branch type Name</label>
                                           <input type="text" class="form-control required" value="<?php echo set_value('branch_type_name'); ?>" id="branch_type_name" name="branch_type_name" >
                                       </div>

                                   </div>
                                   <div class="col-md-12">
                                       <div class="form-group">
                                           <label for="branch_type_image">Image</label>
                                           <input type="file" class="form-control required" value="<?php echo set_value('branch_type_image'); ?>" id="branch_type_image" name="branch_type_image" >
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
<script src="<?php echo base_url(); ?>assets/js/addBranchtype.js" type="text/javascript"></script>
