<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-users"></i> User Management
          <small>Add / Edit User</small>
        </h1>
      </section>

      <section class="content">

          <div class="row">
               <!-- left column -->
               <div class="col-md-8">
                 <!-- general form elements -->



                  <div class="box box-primary">
                       <div class="box-header">
                           <h3 class="box-title">Enter User Details</h3>
                       </div><!-- /.box-header -->
                       <!-- form start -->
                       <?php $this->load->helper("form"); ?>
                       <form role="form" id="addUser" action="<?php echo base_url() ?>addNewUser" method="post" role="form">
                           <div class="box-body">
                             <?php
                             if(empty($BranchID)){


                              ?>
                             <div class="row">
                                 <div class="col-md-6">
                                   <div class="form-group">
                                       <label for="group_id">User Group <span class="glyphicon glyphicon-question-sign btn-lg" data-toggle="tooltip" data-placement="top" title="กรณีเลือก Branch ต้องเลือก ข้อมูล Branch"></span></label>
                                       <select class="form-control required" id="group_id" name="group_id" >
                                           <option value="0">Select Group</option>
                                           <?php
                                           if(!empty($usergroups))
                                           {
                                               foreach ($usergroups as $gl)
                                               {
                                                   ?>
                                                   <option value="<?php echo $gl->id ?>" <?php if($gl->id == set_value('group_id')) {echo "selected=selected";} ?>><?php echo $gl->name ?></option>
                                                   <?php
                                               }
                                           }
                                           ?>
                                       </select>
                                   </div>
                                 </div>

                             </div>

                             <div class="row">
                                 <div class="col-md-6">
                                   <div class="form-group">
                                       <label for="branch_type">Branch Type</label>
                                       <select class="form-control required" id="branch_type" name="branch_type" onchange="JavaScript:list_recommend_do_ajax(document.getElementById('branch_type').value)">
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
                                         <label for="branch_id">Branch</label>

                                         <div id="department_one"><select class="form-control required" id="branch_id" name="branch_id"><option value="0">Select Branch</option>></select></div>
                                     </div>
                                 </div>
                             </div>
                             <?php
                           }
                              ?>
                               <div class="row">
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="fname">Full Name</label>
                                           <input type="text" class="form-control required" value="<?php echo set_value('fname'); ?>" id="fname" name="fname" maxlength="128">
                                       </div>

                                   </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="email">Email address</label>
                                           <input type="text" class="form-control required email" id="email" value="<?php echo set_value('email'); ?>" name="email" maxlength="128">
                                       </div>
                                   </div>
                               </div>
                               <div class="row">
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="password">Password</label>
                                           <input type="password" class="form-control required" id="password" name="password" maxlength="20">
                                       </div>
                                   </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="cpassword">Confirm Password</label>
                                           <input type="password" class="form-control required equalTo" id="cpassword" name="cpassword" maxlength="20">
                                       </div>
                                   </div>
                               </div>
                               <div class="row">
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="mobile">Mobile Number</label>
                                           <input type="text" class="form-control required digits" id="mobile" value="<?php echo set_value('mobile'); ?>" name="mobile" maxlength="10">
                                       </div>
                                   </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="role">Role</label>
                                           <select class="form-control required" id="role" name="role">
                                               <option value="0">Select Role</option>
                                               <?php
                                               if(!empty($roles))
                                               {
                                                   foreach ($roles as $rl)
                                                   {
                                                       ?>
                                                       <option value="<?php echo $rl->roleId ?>" <?php if($rl->roleId == set_value('role')) {echo "selected=selected";} ?>><?php echo $rl->role ?></option>
                                                       <?php
                                                   }
                                               }
                                               ?>
                                           </select>
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
<?php
if(empty($BranchID)){

 ?>
<script src="<?php echo base_url(); ?>assets/js/addUser.js" type="text/javascript"></script>
<?php
}else{
  ?>
  <script src="<?php echo base_url(); ?>assets/js/addUserB.js" type="text/javascript"></script>
  <?php
}
 ?>
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
