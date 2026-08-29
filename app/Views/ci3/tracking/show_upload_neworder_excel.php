<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-link"></i> Data Upload New REQUEST file Management
        </h1>
      </section>
      <section class="content">
  <form action="<?php echo base_url() ?>ExcelNewOrderConfirm" method="POST" id="sendorderUpdate">
          <div class="row">
               <div class="col-xs-12">
                 <div class="box">
                  <div class="box-header">

                       <h3 class="box-title">File List</h3>   &nbsp; &nbsp; <button name="submit_list" id="submit_list" type="button" class="btn btn-primary" style="display:none">Confirm Data </button><button name="submit_list_temp" id="submit_list_temp" type="button" class="btn btn-primary disabled" style="display:none">Confirm Data </button>
                  </div><!-- /.box-header -->

                  <div class="box-body table-responsive no-padding">
                     <table class="table table-hover">
                       <tr>
                         <th>Id</th>
                         <th>เล่มที่/เลขที่</th>
                         <th>Name</th>
                         <th>Telephone</th>
                         <th>Update time</th>
                         <th>Status</th>
                         <th>วันรับซ่อม</th>
                         <th>ราคาซ่อม</th>
                         <th>Waranty</th>
                       </tr>
                       <tr>
                       <td><input type="hidden" id="count_ex" name="count_ex" value="<?php echo count($sheet_data);?>"></td>

                      </tr>
                      <?php
                      $data_class_error=0; $data_sum_trackID="";
                      if(!empty($sheet_data))
                      {
                        $xi=1;$result_status_s=0;$action_status="";$trackID="";$countorderID=0;$data_err_orderid="";

                          foreach($sheet_data as $record)
                          {

                            $trackID=$record->trackID;
                            $action_status=$record->action_status;
                            $waranty_cmg=$record->temp_waranty_cmg;
                            if(!empty($trackID) or $trackID !="")
                            {
                              $class_error="class='error-table'";
                              $data_class_error="";
                              $data_err_orderid=" (เล่มที่/เลขที่ ซ้ำในระบบ) ";
                            }else
                            {
                              $class_error="";
                              $data_err_orderid="";
                            }
                         ?>
                            <tr <?php echo $class_error;?>>
                              <td><?php echo $xi; ?></td>
                              <td><?php echo $record->temp_orderIDShow.$data_err_orderid; ?><input type="hidden" id="orderID<?php echo $xi ?>" name="orderID<?php echo $xi ?>" value="<?php echo $record->temp_orderID ?>"></td>
                              <td><?php echo $record->temp_customerFullname; ?><input type="hidden" id="Name<?php echo $xi ?>" name="Name<?php echo $xi ?>" value="<?php echo $record->temp_customerFullname ?>"></td>


                              <td><?php echo $record->temp_customerTel; ?><input type="hidden" id="Tel<?php echo $xi ?>" name="Tel<?php echo $xi ?>" value="<?php echo $record->temp_customerTel; ?>"></td>
                              <td><?php echo $record->temp_Update ?><input type="hidden" id="updatetime<?php echo $xi ?>" name="updatetime<?php echo $xi ?>" value="<?php echo $record->temp_Update; ?>"></td>
                              <td><?php echo  $record->temp_Status ?><input type="hidden" id="Userstatus<?php echo $xi ?>" name="Userstatus<?php echo $xi ?>" value="<?php echo $record->temp_Status; ?>"></td>
                              <td><?php echo $record->temp_recripUpdate ?><input type="hidden" id="startdate<?php echo $xi ?>" name="startdate<?php echo $xi ?>" value="<?php echo $record->temp_recripUpdate;?>"></td>
                              <td><?php echo $record->temp_pic ?><input type="hidden" id="RepairPrice<?php echo $xi ?>" name="RepairPrice<?php echo $xi ?>" value="<?php echo $record->temp_pic; ?>"></td>
                                <td><?php echo $waranty_cmg ?><input type="hidden" id="waranty_cmg<?php echo $xi ?>" name="waranty_cmg<?php echo $xi ?>" value="<?php echo $waranty_cmg; ?>"></td>
                            </tr>

                       <?php

                       $xi++;
                           }
                           ?>

                            <?php
                       }
                       ?>
                     </table>

                  </div><!-- /.box-body -->

  </form>

                 </div><!-- /.box -->
               </div>
          </div>
      </section>
    </div>

</div>
<script type="text/javascript">
var data_class_error="<?php echo $data_class_error;?>";
if(data_class_error==1)
{
  $(document).ready(function(){
      //$('#submit_list').cc('btn btn-primary disabled');
  $('#submit_list').css('display','none');
  $('#submit_list_temp').css('display','block');
  });
}else{
  $(document).ready(function(){

  $('#submit_list').css('display','block');
  $('#submit_list_temp').css('display','none');

  });
}
$( "#submit_list" ).click(function() {
  var count_ex=document.getElementById('count_ex').value;
  var yy = new Date();
      yy.getFullYear();
  var tid=null;var Userstatus=null;var RepairPrice=null;var mobile=0;var updatetime=0;var startdate=0;var waranty_cmg=null;
  for (var i = 1; i <= count_ex; i++)

  {

    tid=document.getElementById('orderID'+i).value;
    mobile=document.getElementById('Tel'+i).value;

    updatetime=document.getElementById('updatetime'+i).value;
    var res = updatetime.substring(6,10);
    //alert(Number(res));
    startdate=document.getElementById('startdate'+i).value;
    var start_res = startdate.substring(6,10);
    waranty_cmg=document.getElementById('waranty_cmg'+i).value;
    if(tid==null || tid=="" || tid=="undefined")
    {
      alert('กรุณาตรวจสอบข้อมูล เลขที่/เล่มที่');
      return false;
    }
    if(mobile.length !== 10){
      //$('class_tel_error'+i).css('background-color', 'red');
      alert('กรุณาตรวจสอบข้อมูล เบอร์ไม่ถูกต้อง แถวที่'+i);
      return false;
    }
    if(updatetime.length !== 10){
      alert('กรุณาตรวจสอบข้อมูล update time มีดังนี้ 00/00/0000  แถวที่'+i);
      return false;
    }
    if(Number(res)>yy){
      alert('กรุณาตรวจสอบข้อมูล update time ไม่ใช่ ค.ศ  แถวที่'+i);
      return false;
    }
    if(startdate.length !== 10){
      alert('กรุณาตรวจสอบข้อมูลวันที่รับเข้า มีดังนี้ 00/00/0000 แถวที่'+i);
      return false;
    }
    if(Number(start_res)>yy){
      alert('กรุณาตรวจสอบข้อมูลวันที่รับเข้า  ไม่ใช่ ค.ศ  แถวที่'+i);
      return false;
    }
    Userstatus=document.getElementById('Userstatus'+i).value;
    if(Userstatus==null || Userstatus=="" || Userstatus=="undefined")
    {
      alert('กรุณาตรวจสอบข้อมูล Status');
      return false;
    }
    RepairPrice=document.getElementById('RepairPrice'+i);
    if(RepairPrice==null || RepairPrice=="" || RepairPrice=="undefined")
    {
      alert('กรุณาตรวจสอบข้อมูล ราคาซ่อม');
      return false;
    }
    if(waranty_cmg==null || waranty_cmg=="" || waranty_cmg=="undefined")
    {
      alert('กรุณาตรวจสอบข้อมูล waranty');
      return false;
    }
  }
    setTimeout(function(){

    $( "#sendorderUpdate" ).submit();
  },1000);

});
</script>
