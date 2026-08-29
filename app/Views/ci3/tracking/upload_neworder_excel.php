<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-users"></i> Upload NEW REQUEST Management
          <small>Add / Upload</small>
        </h1>
      </section>

      <section class="content">

          <div class="row">
               <!-- left column -->
               <div class="col-md-12">
                 <!-- general form elements -->



                  <div class="box box-primary">
                       <div class="box-header">
                           <h3 class="box-title">Enter Upload Details</h3>
                       </div><!-- /.box-header -->
                       <!-- form start -->
                       <?php $this->load->helper("form"); ?>
                       <form role="form" id="addUser" action="<?php echo base_url() ?>ExcelNewOrderDataAdd" method="post" role="form" enctype="multipart/form-data">
                           <div class="box-body">

                               <div class="row">
                                   <div class="col-md-12">
                                       <div class="form-group">
                                           <label for="fname">Excel File:</label>
                                           <input type="file" name="file" class="form-control">

                                       </div>

                                   </div>

                               </div>


                           </div><!-- /.box-body -->

                           <div class="box-footer">
                               <input  type="submit" name="Submit"  class="btn btn-primary" value="Upload" />
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
<script src="<?php echo base_url(); ?>assets/js/addUser.js" type="text/javascript"></script>
<script type="text/javascript">
var request = null;
function h_recommend_createXMLRequest() {
  try {
    request = new XMLHttpRequest();
  } catch (trymicrosoft) {
    try {
      request = new ActiveXObject("Msxm12.XMLHTTP");
    }	catch (othermicrosoft) {
      try {
        request = new ActiveXObject("Microsoft.XMLHTTP");
      }	catch (failed) {
        request = null;
      }
    }
  }
  if (request == null)
    alert("Error creating request object!");
}

function list_recommend_do_ajax(id)
{
   h_recommend_createXMLRequest();
    var url="<?php echo base_url(); ?>user/get_list_branch/"+id;
  // window.alert(url);
  request.open("GET", url, true);
  request.onreadystatechange = view_recommend_update;
  request.send(null);

}

function view_recommend_update()
{
   // window.alert('OUT');
    if (request.readyState == 4)
    {
    document.getElementById('department_one').innerHTML=request.responseText;
   }
}
</script>
