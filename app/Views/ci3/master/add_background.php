<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-users"></i> background Web EN Management
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
                          <h3 class="box-title">Enter background Details</h3>
                      </div><!-- /.box-header -->
                      <!-- form start -->
                      <?php $this->load->helper("form"); ?>
                      <form role="form" id="addbackground" action="<?php echo base_url() ?>addBackground"  method="post" role="form" enctype="multipart/form-data">
                          <div class="box-body">
                              <div class="row">
                                <div class="col-md-3">
                                  <div class="form-group">
                                      <label for="image_track_laptop">image track aptop (en) </label>
                                       <input type="file" class="form-control required" value="<?php echo set_value('image_track_laptop'); ?>" id="image_track_laptop" name="image_track_laptop" accept="image/*">
                                  </div>
                                </div>
                                  <div class="col-md-3">
                                      <div class="form-group">
                                        <label for="image_track_mobile">image track mobile  (en) </label>
                                         <input type="file" class="form-control required" value="<?php echo set_value('image_track_mobile'); ?>" id="image_track_mobile" name="image_track_mobile"accept="image/*" >
                                      </div>

                                  </div>



                              </div>
                              <div class="row">
                                <div class="col-md-3">
                                  <div class="form-group">
                                      <label for="image_trackstatus_laptop">image trackstatus laptop  (en) </label>
                                       <input type="file" class="form-control required" value="<?php echo set_value('image_trackstatus_laptop'); ?>" id="image_trackstatus_laptop" name="image_trackstatus_laptop" accept="image/*">
                                  </div>
                                </div>
                                  <div class="col-md-3">
                                      <div class="form-group">
                                        <label for="image_trackstatus_mobile">image trackstatus mobile  (en) </label>
                                         <input type="file" class="form-control required" value="<?php echo set_value('image_trackstatus_mobile'); ?>" id="image_trackstatus_mobile" name="image_trackstatus_mobile" accept="image/*">
                                      </div>

                                  </div>

                              </div>
                              <div class="row">
                                <div class="col-md-3">
                                  <div class="form-group">
                                      <label for="image_trackstatus_laptop">image contact laptop  (en) </label>
                                       <input type="file" class="form-control required" value="<?php echo set_value('image_contact_laptop'); ?>" id="image_contact_laptop" name="image_contact_laptop"accept="image/*" >
                                  </div>
                                </div>
                                  <div class="col-md-3">
                                      <div class="form-group">
                                        <label for="image_contact_mobile">image contact mobile  (en) </label>
                                         <input type="file" class="form-control required" value="<?php echo set_value('image_contact_mobile'); ?>" id="image_contact_mobile" name="image_contact_mobile" accept="image/*">
                                      </div>

                                  </div>
                                
                              </div>

                              <div class="row">
                                  <div class="col-md-12">
                                      <div class="form-group">
                                          <label for="status">Publishing Status </label>
                                          <input type="radio" name="status" id="status" value="1" >Yes
                                					<input type="radio" name="status" id="status" value="0" checked="checked">No
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
