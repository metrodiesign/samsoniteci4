<?php

$userId = '';
$name = '';
$email = '';
$mobile = '';
$roleId = '';
$branch_type = '';
$branch_id = '';
$group_id='';
if(!empty($userInfo))
{
    foreach ($userInfo as $uf)
    {
        $userId = $uf->userId;
        $name = $uf->name;
        $email = $uf->email;
        $mobile = $uf->mobile;
        $roleId = $uf->roleId;
        $branch_type = $uf->branch_type_id;
        $branch_id = $uf->branch_id;
        $group_id = $uf->group_id;
    }
}


?>

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
               <div class="col-md-12">
                 <!-- general form elements -->



                  <div class="box box-primary">
                       <div class="box-header">
                           <h3 class="box-title">Enter User Details</h3>
                       </div><!-- /.box-header -->
                       <!-- form start -->

                       <form role="form" action="<?php echo base_url() ?>editUser" method="post" id="editUser" role="form">
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
                                                   <option value="<?php echo $gl->id ?>" <?php if($gl->id == $group_id) {echo "selected=selected";} ?>><?php echo $gl->name ?></option>
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
                                                   <option value="<?php echo $rl->branch_type_id ?>" <?php if($rl->branch_type_id == $branch_type) {echo "selected=selected";} ?>><?php echo $rl->branch_type_details ?></option>
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
                                         <div id="department_one">
                                           <select class="form-control required" id="branch_id" name="branch_id"><option value="0">Select Branch</option>
                                             <?php
                                             if(!empty($branchs))
                                             {
                                                 foreach ($branchs as $bl)
                                                 {
                                                     ?>
                                                     <option value="<?php echo $bl->branch_id ?>" <?php if($bl->branch_id == $branch_id) {echo "selected=selected";} ?>><?php echo $bl->branch_name ?></option>
                                                     <?php
                                                 }
                                             }
                                             ?>
                                           </select>
                                         </div>
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
                                           <input type="text" class="form-control" id="fname" placeholder="Full Name" name="fname" value="<?php echo $name; ?>" maxlength="128">
                                           <input type="hidden" value="<?php echo $userId; ?>" name="userId" id="userId" />
                                       </div>

                                   </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="email">Email address</label>
                                           <input type="email" class="form-control" id="email" placeholder="Enter email" name="email" value="<?php echo $email; ?>" maxlength="128">
                                       </div>
                                   </div>
                               </div>
                               <div class="row">
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="password">Password</label>
                                           <input type="password" class="form-control" id="password" placeholder="Password" name="password" maxlength="20">
                                       </div>
                                   </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="cpassword">Confirm Password</label>
                                           <input type="password" class="form-control" id="cpassword" placeholder="Confirm Password" name="cpassword" maxlength="20">
                                       </div>
                                   </div>
                               </div>
                               <div class="row">
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="mobile">Mobile Number</label>
                                           <input type="text" class="form-control" id="mobile" placeholder="Mobile Number" name="mobile" value="<?php echo $mobile; ?>" maxlength="10">
                                       </div>
                                   </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="role">Role</label>
                                           <select class="form-control" id="role" name="role">
                                               <option value="0">Select Role</option>
                                               <?php
                                               if(!empty($roles))
                                               {
                                                   foreach ($roles as $rl)
                                                   {
                                                       ?>
                                                       <option value="<?php echo $rl->roleId; ?>" <?php if($rl->roleId == $roleId) {echo "selected=selected";} ?>><?php echo $rl->role ?></option>
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
<script src="<?php echo base_url(); ?>assets/js/editUser.js" type="text/javascript"></script>
<?php
}else{
  ?>
<script src="<?php echo base_url(); ?>assets/js/editUserB.js" type="text/javascript"></script>
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
