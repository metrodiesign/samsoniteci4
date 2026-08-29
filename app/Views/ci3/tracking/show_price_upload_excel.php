<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-link"></i> Data Upload file Price Management
        </h1>
      </section>
      <section class="content">
  <form action="<?php echo base_url() ?>ExcelPriceConfirm" method="POST" id="sendorderUpdate">
          <div class="row">
               <div class="col-xs-12">
                 <div class="box">
                  <div class="box-header">

                       <h3 class="box-title">File List</h3>   &nbsp; &nbsp; <button name="submit_list" id="submit_list" type="button" class="btn btn-primary" style="display:none">Confirm Data </button><button name="submit_list_temp" id="submit_list_temp" type="button" class="btn btn-primary disabled" style="display:none">Confirm Data </button>
                  </div><!-- /.box-header -->

                  <div class="box-body table-responsive no-padding">
                     <table class="table table-hover" >
                       <tr id="header_id">
                         <th>Id</th>
                         <th>เล่มที่/เลขที่</th>
                         <th>Name</th>
                         <th>Telephone</th>
                         <th>Update time</th>
                         <th>Status</th>
                         <th>วันรับซ่อม</th>
                         <th>ราคาซ่อม</th>
                         <th>Waranty</th>
                        <th>เลขที่ CMG</th>
                       </tr>
                       <tr>
                       <td><input type="hidden" id="count_ex" name="count_ex" value="<?php echo count($sheet_data);?>"></td>

                      </tr>
                      <?php
                      $data_class_error=0; $data_sum_trackID="";$data_err_orderid="";
                      if(!empty($sheet_data))
                      {
                        //var_dump($sheet_data);
                        $xi=1;$result_status_s=0;$action_status="";$trackID="";$countorderID=0;$data_err_orderid="";$si=0;

                          foreach($sheet_data as $record)
                          {

                            $trackID=$record->trackID;
                            $action_status=$record->action_status;
                            $countorderID=$record->countorderID;
                            $waranty_cmg=$record->temp_waranty_cmg;
                            $temp_orderIDShow=$record->temp_orderIDShow;
                            if(empty($trackID) or $trackID=="")
                            {
                              $class_error="class='error-table'";
                              $data_class_error=1;
                              $low_temp_orderIDShow=$this->request_order_model->get_orderIDShowBytel($temp_orderIDShow);
                              if($low_temp_orderIDShow==1){
                                $data_err_orderid=" (เลขที่ CMG ไม่ตรงกับระบบ) ";
                              }else{
                                $data_err_orderid=" ( เลขที่ CMG ซ้ำกันในระบบ) ";
                              }

                            }else
                            {
                              if($countorderID>1){
                                $class_error="class='yellow-table'";
                              //  $data_class_error=1;
                                $data_err_orderid=" (เลขที่ CMG มีมากกว่า 1) ";
                              }else{
                                if($action_status ==1){
                                  $class_error="class='newstatus-table'";
                                }else{

                                  $class_error="";
                                }
                                $data_err_orderid="";
                              }

                            }
                            $temp_Status=$record->temp_Status;
                            $temp_Update=$record->temp_Update;
                            $temp_recripUpdate=$record->temp_recripUpdate;
                            $temp_pic=$record->temp_pic;
                            $temp_number_cmg=$record->temp_number_cmg;
                            $yyyy=date("Y")+1;
                            //echo substr($temp_recripUpdate,-4);
                            if($class_error <>"" or $temp_pic=="" or $temp_number_cmg=="")
                            {
                              $si++;
                              $dis_style='';
                            }else{
                              $dis_style=' style="display:none"';
                            }
                         ?>
                            <tr <?php echo $class_error.$dis_style;?> >
                              <td><?php echo $xi; ?></td>
                              <td><?php echo $record->temp_orderIDShow.$data_err_orderid; ?><input type="hidden" id="orderID<?php echo $xi ?>" name="orderID<?php echo $xi ?>" value="<?php echo $record->temp_orderID ?>"></td>
                              <td><?php echo $record->temp_customerFullname; ?><input type="hidden" id="Name<?php echo $xi ?>" name="Name<?php echo $xi ?>" value="<?php echo $record->temp_customerFullname ?>"></td>


                              <td id="class_tel_error<?php echo $xi; ?>"><?php echo $record->temp_customerTel; ?><input type="hidden" id="Tel<?php echo $xi ?>" name="Tel<?php echo $xi ?>" value="<?php echo $record->temp_customerTel; ?>"></td>
                              <td><?php echo $record->temp_Update ?><input type="hidden" id="updatetime<?php echo $xi ?>" name="updatetime<?php echo $xi ?>" value="<?php echo $record->temp_Update; ?>"></td>
                              <td><?php echo  $record->temp_Status ?><input type="hidden" id="Userstatus<?php echo $xi ?>" name="Userstatus<?php echo $xi ?>" value="<?php echo $record->temp_Status; ?>"></td>
                              <td><?php echo $record->temp_recripUpdate ?><input type="hidden" id="startdate<?php echo $xi ?>" name="startdate<?php echo $xi ?>" value="<?php echo $record->temp_recripUpdate;?>"></td>
                              <td><?php echo $record->temp_pic ?><input type="hidden" id="RepairPrice<?php echo $xi ?>" name="RepairPrice<?php echo $xi ?>" value="<?php echo $record->temp_pic; ?>"></td>
                              <td><?php echo $waranty_cmg ?><input type="hidden" id="waranty_cmg<?php echo $xi ?>" name="waranty_cmg<?php echo $xi ?>" value="<?php echo $waranty_cmg; ?>"></td>
                              <td><?php echo $temp_number_cmg ?><input type="hidden" id="number_cmg<?php echo $xi ?>" name="number_cmg<?php echo $xi ?>" value="<?php echo $temp_number_cmg; ?>"></td>
                            </tr>
                            <tr <?php echo $class_error.$dis_style;?>>
                             <td></td>
                            <td>
                                <?php echo "Branch:".$record->branch_name;?>
                                <input type="hidden" id="trackID<?php echo $xi ?>" name="trackID<?php echo $xi ?>" value="<?php echo $record->trackID ?>">

                            </td>
                            <td>
                                <?php echo "Customer Name:".$record->customerFullname;?>
                            </td>
                            <td>
                                <?php

                                $requestDate =  $record->requestDate;
                                $dd=$requestDate;
                                $AA=substr($dd,0,4);
                                $BB=substr($dd,5,2);
                                $CC=substr($dd,8,2);
                                $DD=$AA;
                                $data_requestDate=$CC."/".$BB."/".$DD;
                                 echo "request Date:".$data_requestDate;
                                 ?>
                            </td>
                            <td>Status : <?php echo $record->status_name_th;?></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                           </tr>
                       <?php

                       $xi++;
                           }

                            if($si==0){
                              echo "ข้อมูลถูกต้อง กรุณากด Comfirm เพื่ออัพโหลดราคา";
                              ?>
                              <script type="text/javascript">
                              $(document).ready(function(){
                                  //$('#submit_list').cc('btn btn-primary disabled');
                              $('#header_id').css('display','none');
                              $('#submit_list').css('display','block');
                              $('#submit_list_temp').css('display','none');
                              });
                              </script>
                              <?php
                            }else{
                              echo "กรุณากรอกราคาสินค้าซ่อมเสร็จแล้ว";
                              ?>
                              <script type="text/javascript">
                              $(document).ready(function(){
                                  //$('#submit_list').cc('btn btn-primary disabled');

                                  $('#submit_list').css('display','none');
                                  $('#submit_list_temp').css('display','block');
                              });
                              </script>
                              <?php
                            }


                       }else{
                          echo "ไม่พบข้อมูลสินค้าซ่อมเสร็จแล้วในระบบ";
                          ?>
                          <script type="text/javascript">
                          $(document).ready(function(){
                              //$('#submit_list').cc('btn btn-primary disabled');
                          $('#header_id').css('display','none');
                          });
                          </script>
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

$( "#submit_list" ).click(function() {
  var data_m_txt="";
  var yy = new Date();
      yy.getFullYear()+1;
  var count_ex=document.getElementById('count_ex').value;
  var tid=null;var Userstatus=null;var RepairPrice=null;var mobile=0;var updatetime=0;var startdate=0;var waranty_cmg=null;var number_cmg=null;
  for (var i = 1; i <= count_ex; i++)

  {
    tid=document.getElementById('trackID'+i).value;
    mobile=document.getElementById('Tel'+i).value;
    updatetime=document.getElementById('updatetime'+i).value;
    var res = updatetime.substring(6,10);
    //alert(res);
    startdate=document.getElementById('startdate'+i).value;
    var start_res = startdate.substring(6,10);
    waranty_cmg=document.getElementById('waranty_cmg'+i).value;
    number_cmg=document.getElementById('number_cmg'+i).value;
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


    RepairPrice=document.getElementById('RepairPrice'+i);
    if(RepairPrice==null || RepairPrice=="" || RepairPrice=="undefined")
    {
      alert('กรุณาตรวจสอบข้อมูล ราคาซ่อม');
      return false;
    }
    if(number_cmg==null || number_cmg=="" || number_cmg=="undefined")
    {
      alert('กรุณาตรวจสอบข้อมูล เลขที่ CMG');
      return false;
    }

  }

    setTimeout(function(){

    $( "#sendorderUpdate" ).submit();
  },1000);

});
</script>
