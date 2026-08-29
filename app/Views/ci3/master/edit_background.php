<?php
$id="";
$image_track_laptop="";
$image_track_mobile="";
$image_trackstatus_laptop="";
$image_trackstatus_mobile="";
$image_contact_laptop="";
$image_contact_mobile="";
$image_track_laptop_th="";
$image_track_mobile_th="";
$image_trackstatus_laptop_th="";
$image_trackstatus_mobile_th="";
$image_contact_laptop_th="";
$image_contact_mobile_th="";
$status="";
if(!empty($BackgroundInfo))
{
  foreach ($BackgroundInfo as $uf)
  {
    $id=$uf->id;
    $image_track_laptop=$uf->image_track_laptop;
    $image_track_mobile=$uf->image_track_mobile;
    $image_trackstatus_laptop=$uf->image_trackstatus_laptop;
    $image_trackstatus_mobile=$uf->image_trackstatus_mobile;
    $image_contact_laptop=$uf->image_contact_laptop;
    $image_contact_mobile=$uf->image_contact_mobile;

    $image_track_laptop_th=$uf->image_track_laptop_th;
    $image_track_mobile_th=$uf->image_track_mobile_th;
    $image_trackstatus_laptop_th=$uf->image_trackstatus_laptop_th;
    $image_trackstatus_mobile_th=$uf->image_trackstatus_mobile_th;
    $image_contact_laptop_th=$uf->image_contact_laptop_th;
    $image_contact_mobile_th=$uf->image_contact_mobile_th;
    $status=$uf->status;
  }
}
 ?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <div class="row">
            <div class="col-xs-12">
               <h1>
                 <i class="fa fa-users"></i> background Web EN Management
                 <small>Add / Edit Branch</small>
               </h1>
            </div>
        </div>
      </section>
      <section class="content">

          <div class="row">
              <!-- left column -->
              <div class="col-md-12">
                <!-- general form elements -->



                  <div class="box box-primary">
                      <!-- <div class="box-header">
                          <h3 class="box-title">Enter background Details</h3>
                      </div> -->
                      <!-- /.box-header -->
                      <!-- form start -->
                      <?php $this->load->helper("form"); ?>
                      <form role="form" id="editBackground" action="<?php echo base_url() ?>editBackground"  method="post" role="form" enctype="multipart/form-data">
                          <div class="box-body">
                              <div class="row">
                                <div class="col-md-12">
                                  <div class="box-header">
                                      <h3 class="box-title" style="color: #024c8e; font-weight: 700;">ENTER BACKGROUND: TRACK & TRACE</h3>
                                  </div><!-- /.box-header -->
                                  <div class="col-md-6">

                                    <div class="form-group">

                                        <label for="image_track_laptop">laptop size 1920px (en)</label>

                                        <input type="hidden" value="<?php echo $id; ?>" name="background_id" id="background_id" />

                                         <input type="file" class="form-control required" id="image_track_laptop" name="image_track_laptop" accept="image/*">

                                         <p>

                                          <?php

                                          if($image_track_laptop)

                                          {

                                              echo '<img src="'.base_url().$image_track_laptop.'" class="img-responsive">';

                                          }else{

                                              echo 'Not Data';

                                          }

                                          ?>

                                        </p>

                                    </div>

                                  </div>

                                    <div class="col-md-6">

                                        <div class="form-group">

                                          <label for="image_track_mobile">mobile size 480px (en)</label>

                                           <input type="file" class="form-control required"  id="image_track_mobile" name="image_track_mobile"accept="image/*" >

                                           <p>

                                            <?php

                                            if($image_track_mobile)

                                            {

                                                echo '<img src="'.base_url().$image_track_mobile.'" class="img-responsive">';

                                            }else{

                                                echo 'Not Data';

                                            }

                                            ?>

                                          </p>

                                        </div>



                                    </div>




                                </div>


                              </div>
                              <div class="row">
                                <div class="col-md-12">
                                  <div class="box-header">
                                      <h3 class="box-title" style="color: #024c8e; font-weight: 700;">ENTER BACKGROUND: TRACK STATUS</h3>
                                  </div><!-- /.box-header -->
                                  <div class="col-md-6">

                                    <div class="form-group">

                                        <label for="image_trackstatus_laptop">laptop size 1920px (en)</label>

                                         <input type="file" class="form-control required" id="image_trackstatus_laptop" name="image_trackstatus_laptop" accept="image/*">

                                         <p>

                                          <?php

                                          if($image_trackstatus_laptop)

                                          {

                                              echo '<img src="'.base_url().$image_trackstatus_laptop.'" class="img-responsive">';

                                          }else{

                                              echo 'Not Data';

                                          }

                                          ?>

                                        </p>

                                    </div>

                                  </div>

                                    <div class="col-md-6">

                                        <div class="form-group">

                                          <label for="image_trackstatus_mobile">mobile size 480px (en)</label>

                                           <input type="file" class="form-control required"  id="image_trackstatus_mobile" name="image_trackstatus_mobile" accept="image/*">

                                           <p>

                                            <?php

                                            if($image_trackstatus_mobile)

                                            {

                                                echo '<img src="'.base_url().$image_trackstatus_mobile.'" class="img-responsive">';

                                            }else{

                                                echo 'Not Data';

                                            }

                                            ?>

                                          </p>

                                        </div>



                                    </div>





                                </div>


                              </div>
                              <div class="row">
                                <div class="col-md-12">
                                  <div class="box-header">
                                      <h3 class="box-title" style="color: #024c8e; font-weight: 700;">ENTER BACKGROUND: CONTACT US</h3>
                                  </div><!-- /.box-header -->
                                  <div class="col-md-6">

                                    <div class="form-group">

                                        <label for="image_trackstatus_laptop">laptop size 1920px (en)</label>

                                         <input type="file" class="form-control required" id="image_contact_laptop" name="image_contact_laptop"accept="image/*" >

                                         <p>

                                          <?php

                                          if($image_contact_laptop)

                                          {

                                              echo '<img src="'.base_url().$image_contact_laptop.'" class="img-responsive">';

                                          }else{

                                              echo 'Not Data';

                                          }

                                          ?>

                                        </p>

                                    </div>

                                  </div>

                                    <div class="col-md-6">

                                        <div class="form-group">

                                          <label for="image_contact_mobile">mobile size 480px (en)</label>

                                           <input type="file" class="form-control required" id="image_contact_mobile" name="image_contact_mobile" accept="image/*">

                                           <p>

                                            <?php

                                            if($image_contact_mobile)

                                            {

                                                echo '<img src="'.base_url().$image_contact_mobile.'" class="img-responsive">';

                                            }else{

                                                echo 'Not Data';

                                            }

                                            ?>

                                          </p>

                                        </div>



                                    </div>





                                </div>


                              </div>

                              <div class="row">
                                  <div class="col-md-12">
                                      <div class="form-group">
                                          <label for="status">Publishing Status </label>
                                          <?php
                                      if($status==1){
                                      $checked = 'checked="checked"';
                                      $checked2="";
                                      }else{
                                      $checked="";
                                      $checked2 = 'checked="checked"';
                                      }
                                      ?>
                                      <input type="radio" name="status" id="status" value="1" <?php echo $checked;?>> Yes
                                      <input type="radio" name="status" id="status" value="0"  <?php echo $checked2;?>> No
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
