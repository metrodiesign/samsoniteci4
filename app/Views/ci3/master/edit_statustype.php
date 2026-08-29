<?php

$status_id = '';
$branch_type_name = '';
$description_en='';
$success="";
if(!empty($SatusInfo))
{
    foreach ($SatusInfo as $uf)
    {
        $status_id = $uf->status_id;
        $description_th = $uf->description_th;
        $description_en = $uf->description_en;
        $success = $uf->success;
    }
}


?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-users"></i> Status type Management
            <small>Add / Edit Status</small>
        </h1>
      </section>

      <section class="content">

          <div class="row">
               <!-- left column -->
               <div class="col-md-12">
                 <!-- general form elements -->



                  <div class="box box-primary">
                       <div class="box-header">
                           <h3 class="box-title">Enter Status type Details</h3>
                       </div><!-- /.box-header -->
                       <!-- form start -->
                       <?php $this->load->helper("form"); ?>
                       <form role="form" id="addStatustype" action="<?php echo base_url() ?>editStatustype" method="post" role="form">
                           <div class="box-body">
                               <div class="row">

                                 <div class="col-md-6">
                                     <div class="form-group">
                                         <label for="description_th">Description th</label>
                                         <input type="text" class="form-control required" value="<?php echo $description_th; ?>" id="description_th" name="description_th" >
                                         <input type="hidden" class="form-control " value="<?php echo $status_id; ?>" id="status_id" name="status_id" >
                                     </div>

                                 </div>
                                 <div class="col-md-6">
                                     <div class="form-group">
                                         <label for="description_en">Description en</label>
                                         <input type="text" class="form-control required" value="<?php echo $description_en; ?>" id="description_en" name="description_en" >
                                     </div>

                                 </div>
                                 <div class="col-md-6">
                                     <div class="form-group">
                                         <label for="success">Config Status(0/1)</label>
                                         <input type="text" class="form-control" value="<?php echo $success; ?>" id="success" name="success" >
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
<script src="<?php echo base_url(); ?>assets/js/addStatustype.js" type="text/javascript"></script>
