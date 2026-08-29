<?php
//คำสั่ง connect db เขียนเพิ่มเองนะ
$date=date("Y-m-d H:i:s");
$timestamp = strtotime($date);
$strExcelFileName="Report-".$timestamp.".xls";
header("Content-Type: application/x-msexcel; name=\"$strExcelFileName\"");
header("Content-Disposition: inline; filename=\"$strExcelFileName\"");
header("Pragma:no-cache");
//$music_count_list=$this->dashboard_model->music_count();
//var_dump($music_count_list);
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"xmlns:x="urn:schemas-microsoft-com:office:excel"xmlns="http://www.w3.org/TR/REC-html40">

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
<strong>REPORT <br>
<br>
<div id="SiXhEaD_Excel" align=center x:publishsource="Excel">
<table x:str border=1 cellpadding=0 cellspacing=1 width=100% style="border-collapse:collapse">
   <tr>
     <td width="94" height="30" align="center" valign="middle" ><strong>No</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Action Status</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Status Update</strong></td>
     <td width="200" align="center" valign="middle" ><strong>TotalDay</strong></td>
     <?php
     if(empty($BranchID)){
       echo '<td width="200" align="center" valign="middle" ><strong>CMG TotalDay</strong></td>';
     }
      ?>
     <td width="200" align="center" valign="middle" ><strong>Branch User</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Branch Name</strong></td>

     <td width="200" align="center" valign="middle" ><strong>trackID</strong></td>
     <td width="200" align="center" valign="middle" ><strong>orderID</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Urgent</strong></td>
     <td width="200" align="center" valign="middle" ><strong>customerFullname</strong></td>
     <td width="200" align="center" valign="middle" ><strong>customerTel</strong></td>
     <td width="200" align="center" valign="middle" ><strong>customerEmail</strong></td>
     <td width="200" align="center" valign="middle" ><strong>RequestDate</strong></td>
     <td width="200" align="center" valign="middle" ><strong>รับเข้า</strong></td>
     <td width="200" align="center" valign="middle" ><strong>อัพเดทล่าสุด</strong></td>
     <td width="200" align="center" valign="middle" ><strong>ศูนย์ส่งคืนสาขา</strong></td>
     <td width="200" align="center" valign="middle" ><strong>ลูกค้ามารับคืน</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Logistics</strong></td>
     <td width="200" align="center" valign="middle" ><strong>ราคาซ่อม</strong></td>
   </tr>
   <?php
   if(!empty($OrdersRecords))
   {
     $i=1;$detailAgent=  "";
       foreach($OrdersRecords as $record)
       {
         $dd=$record->requestDate;
         $AA=substr($dd,0,4);
         $BB=substr($dd,5,2);
         $CC=substr($dd,8,2);
         $DD=$AA;
         $XX=$CC."/".$BB."/".$DD;
         $detailAgent=  $record->detailAgent;
         if($detailAgent==1){
           $data_detailAgent="มี";
         }else{
           $data_detailAgent="ไม่มี";
         }
         $repair=$record->date_repair;
         if($repair and $repair !=null)
         {
           $repair_a=substr($repair,0,4);
           $repair_b=substr($repair,5,2);
           $repair_c=substr($repair,8,2);
           $repair_d=$repair_a+543;
           $date_repair=$repair_c."/".$repair_b."/".$repair_d;
         }else{

           $date_repair="";
         }

         $update_status=$record->date_update_status;
         if($update_status and $update_status !=null)
         {
           $update_status_a=substr($update_status,0,4);
           $update_status_b=substr($update_status,5,2);
           $update_status_c=substr($update_status,8,2);
           $update_status_d=$update_status_a;
           $date_update_status=$update_status_c."/".$update_status_b."/".$update_status_d;
         }else{
           $date_update_status="";
         }

         $deliver=$record->date_deliver;
         if($deliver and $deliver !=null)
         {
           $deliver_a=substr($deliver,0,4);
           $deliver_b=substr($deliver,5,2);
           $deliver_c=substr($deliver,8,2);
           $deliver_d=$deliver_a;
           $date_deliver=$deliver_c."/".$deliver_b."/".$deliver_d;
         }else{
           $date_deliver="";
         }
         $complete=$record->date_complete;
         if($complete and $complete !=null)
         {
           $complete_a=substr($complete,0,4);
           $complete_b=substr($complete,5,2);
           $complete_c=substr($complete,8,2);
           $complete_d=$complete_a;
           $date_complete=$complete_c."/".$complete_b."/".$complete_d;
         }else{
           $date_complete="";
         }
         if($record->TotalDay==""){
           $data_TotalDay="";
         }else{
           $data_TotalDay=$record->TotalDay;
         }
         if($record->CMGTotalDay==""){
           $data_CMGTotalDay="";
         }else{
           $data_CMGTotalDay=$record->CMGTotalDay+1;
         }


         $provider_id=$record->provider_id;
         $trackID=$record->trackID;
         $orderID=$record->orderID;
         $logistics_etc_detail=$record->logistics_etc_detail;
         if($logistics_etc_detail !="" and $logistics_etc_detail != NULL){
           $data_ProviderName=$logistics_etc_detail;
         }else{
           $ProviderName=$this->request_order_model->getProviderName($provider_id);
          // echo $ProviderName;
          if($ProviderName==""){
            $data_ProviderName="";
           }else{
              $data_ProviderName=$ProviderName;
           }
          }
          $customerTel=$record->customerTel;
         $ststus_update=$this->request_order_model->chack_status_update($trackID,$customerTel);
         $action_status=$record->action_status;
         $url_print=base_url().'OrderPrint/'.$record->request_id;
   ?>
   <tr>
     <td><?php echo $i?></td>
     <td><?php echo $record->status_name ?></td>
     <td><?php echo $ststus_update;?></td>
     <td><?php echo $data_TotalDay;?></td>
     <?php
     if(empty($BranchID)){
       echo '<td>'.$data_CMGTotalDay.'</td>';
     }
      ?>
     <td><?php echo $record->branch_user_name;?></td>
     <td><?php echo $record->branch_name;?></td>
     <td><?php echo $record->trackID ?></td>
     <td><?php echo $record->orderIDShow ?></td>
     <td><?php echo $data_detailAgent;?></td>
     <td><?php echo $record->customerFullname ?></td>
     <td><?php echo $record->customerTel ?></td>
     <td><?php echo $record->customerEmail ?></td>
     <td align="right"><?php echo $XX ?></td>
     <td align="right"><?php echo $date_repair ?></td>
     <td align="right"><?php echo $date_update_status ?></td>
     <td align="right"><?php echo $date_deliver ?></td>
     <td align="right"><?php echo $date_complete ?></td>
     <td>
       <?php echo $data_ProviderName;?>
     </td>
     <td align="right">
       <?php
       if($record->RepairPrice){
         echo number_format($record->RepairPrice,0);
       }
       ?>
     </td>

   </tr>
   <?php
   $i++;
       }
   }
   ?>
 </table>
