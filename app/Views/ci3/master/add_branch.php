<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-users"></i> Branch Management
          <small>Add / Edit Branch</small>
        </h1>
      </section>

      <section class="content">

          <div class="row">
               <!-- left column -->
               <div class="col-md-12">
                 <!-- general form elements -->



                  <div class="box box-primary">
                       <div class="box-header">
                           <h3 class="box-title">Enter branch Details</h3>
                       </div><!-- /.box-header -->
                       <!-- form start -->
                       <?php $this->load->helper("form"); ?>
                       <form role="form" id="addBranch" action="<?php echo base_url() ?>addNewBranch" method="post" role="form">
                           <div class="box-body">
                               <div class="row">
                                 <div class="col-md-6">
                                   <div class="form-group">
                                       <label for="branch_type">Branch Type</label>
                                       <select class="form-control required" id="branch_type" name="branch_type">
                                           <option value="0">Select Branch Type</option>
                                           <?php
                                           if(!empty($branchtypes))
                                           {
                                               foreach ($branchtypes as $rl)
                                               {
                                                   ?>
                                                   <option value="<?php echo $rl->branch_type_id ?>" <?php if($rl->branch_type_id == set_value('branch_type')) {echo "selected=selected";} ?>><?php echo $rl->branch_type_details ?></option>
                                                   <?php
                                               }
                                           }
                                           ?>
                                       </select>
                                   </div>
                                 </div>
                                 <div class="col-md-6">
                                     <div class="form-group">
                                         <label for="branch_user_name">Branch User</label>
                                         <input type="text" class="form-control required" value="<?php echo set_value('branch_user_name'); ?>" id="branch_user_name" name="branch_user_name" >
                                     </div>

                                 </div>



                               </div>
                               <div class="row">
                                 <div class="col-md-6">
                                     <div class="form-group">
                                         <label for="branch_name">Branch Name</label>
                                         <input type="text" class="form-control required" value="<?php echo set_value('branch_name'); ?>" id="branch_name" name="branch_name" >
                                     </div>

                                 </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="default_suffix">PREFIX </label>
                                           <input type="text" class="form-control" id="default_suffix" name="default_suffix" maxlength="7">

                                       </div>
                                   </div>

                                   <div class="col-md-6" style="display:none">
                                       <div class="form-group">
                                           <label for="book_order">book order <span class="glyphicon glyphicon-question-sign btn-lg" data-toggle="tooltip" data-placement="top" title=" เป็นเลขเล่มที่ของสาขา  (2หลัก)"></span></label>
                                           <input type="text" class="form-control" id="book_order" name="book_order" maxlength="2">

                                       </div>
                                   </div>
                               </div>
                               <div class="row">
                                 <div class="col-md-6" >
                                     <div class="form-group">
                                         <label for="customer_ref">Customer Ref </label>
                                         <input type="text" class="form-control" id="customer_ref" name="customer_ref" maxlength="30">

                                     </div>
                                 </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="branch_details">Detail</label>
                                           <textarea class="form-control" rows="5" id="branch_details"  name="branch_details"></textarea>
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
<script src="<?php echo base_url(); ?>assets/js/addBranch.js" type="text/javascript"></script>
