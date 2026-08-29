<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-users"></i> Book Management
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
                          <h3 class="box-title">Enter Book Details</h3>
                      </div><!-- /.box-header -->
                      <!-- form start -->
                      <?php $this->load->helper("form"); ?>
                      <form role="form" id="addBook" action="<?php echo base_url() ?>addNewBook" method="post" role="form">
                          <div class="box-body">
                              <div class="row">
                                <div class="col-md-6">
                                  <div class="form-group">
                                      <label for="branch_id">Branch </label>
                                      <select class="form-control required" id="branch_id" name="branch_id">
                                          <option value="0">Select Branch </option>
                                          <?php
                                          if(!empty($branch_list))
                                          {
                                              foreach ($branch_list as $rl)
                                              {
                                                  ?>
                                                  <option value="<?php echo $rl->branch_id ?>" <?php if($rl->branch_id == set_value('branch_id')) {echo "selected=selected";} ?>><?php echo $rl->branch_name ?></option>
                                                  <?php
                                              }
                                          }
                                          ?>
                                      </select>
                                  </div>
                                </div>
                                  <div class="col-md-6">
                                      <div class="form-group">
                                          <label for="book_detail">Book Detail</label>
                                          <input type="text" class="form-control required" value="<?php echo set_value('book_detail'); ?>" id="book_detail" name="book_detail" >
                                      </div>

                                  </div>

                              </div>

                              <div class="row">
                                  <div class="col-md-12">
                                      <div class="form-group">
                                        <label class="custom-control custom-radio" for="warantyType">Publishing Status</label><BR>
                                           <label class="custom-form">
                                         <input type="radio" name="status" id="status" value="1" checked>
                                               <span class="label-text">Yes</span>
                                       </label>
                                            <label class="custom-form">
                                         <input type="radio" name="status" id="status" value="0">
                                                <span class="label-text">No</span>
                                         </label>
                                      

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
<script src="<?php echo base_url(); ?>assets/js/addBook.js" type="text/javascript"></script>
