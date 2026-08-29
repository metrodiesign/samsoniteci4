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
<strong>REPORT SUMMARY<br>
<br>
<div id="SiXhEaD_Excel" align=center x:publishsource="Excel">
<table x:str border=1 cellpadding=0 cellspacing=1 width=100% style="border-collapse:collapse">
   <tr>
     <td width="94" height="30" align="center" valign="middle" ><strong>No</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Action Status</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Branch User</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Branch Name</strong></td>

     <td width="200" align="center" valign="middle" ><strong>trackID</strong></td>
     <td width="200" align="center" valign="middle" ><strong>orderID</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Urgent</strong></td>
     <td width="200" align="center" valign="middle" ><strong>customerFullname</strong></td>
     <td width="200" align="center" valign="middle" ><strong>customerTel</strong></td>
     <td width="200" align="center" valign="middle" ><strong>customerEmail</strong></td>
     <td width="200" align="center" valign="middle" ><strong>RequestDate</strong></td>
     <th width="200" align="center" valign="middle" >BRAND ID/ยี่ห้อ</th>
     <th width="200" align="center" valign="middle" >CATEGORY/ประเภท</th>
     <th width="200" align="center" valign="middle" >SKU NAME/ชื่อสินค้า</th>
     <th width="200" align="center" valign="middle" >WARANTY/หมายเลขประกัน</th>
     <th width="200" align="center" valign="middle" >EQUIPMENT/อุปกรณ์ที่มาพร้อมกับสินค้า</th>
     <th width="200" align="center" valign="middle" >NOTE/หมายเหตุ</th>
     <th width="200" align="center" valign="middle" >Condition/อาการที่ส่งซ่อมุ</th>
     <th width="200" align="center" valign="middle" >Estimate Price/ประเมินราคาส่งซ่อมุ</th>
     <th width="200" align="center" valign="middle" >Fixed/สภาพ,ตำหนิ</th>
     <td width="200" align="center" valign="middle" ><strong>รับเข้า</strong></td>
     <td width="200" align="center" valign="middle" ><strong>อัพเดทล่าสุด</strong></td>
     <td width="200" align="center" valign="middle" ><strong>ศูนย์ส่งคืนสาขา</strong></td>
     <td width="200" align="center" valign="middle" ><strong>ลูกค้ามารับคืน</strong></td>
     <td width="200" align="center" valign="middle" ><strong>ราคาซ่อม</strong></td>
     <td width="200" align="center" valign="middle" ><strong>Warannty</strong></td>
   </tr>
   <?php
   if(!empty($OrdersRecords))
   {

       $i=0;

     $detailAgent=  "";
       foreach($OrdersRecords as $record)
       {
         $i++;
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
           $repair_d=$repair_a;
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

         $provider_id=$record->provider_id;
         $trackID=$record->trackID;
         $orderID=$record->orderID;
         $Telephone=$record->customerTel;
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
         $ststus_update=$this->request_order_model->chack_status_update($orderID,$Telephone);
         $action_status=$record->action_status;
         $url_print=base_url().'OrderPrint/'.$record->request_id;
         $detailSKUName=$record->detailSKUName;
         $detailNumberWaranty=$record->detailNumberWaranty;
         $brand_details=$record->brand_details;
         $type_details=$record->type_details;
         $detailEquipment=$record->detailEquipment;
         $detailNote=$record->detailNote;

         $detailCondition=$record->detailCondition;
         $detailEstimatePrice=$record->detailEstimatePrice;
         $detailFixed=$record->detailFixed;
         $detailConditionOther=  $record->detailConditionOther;
         $detailEstimatePriceOther=  $record->detailEstimatePriceOther;
         $detailFixedOther=  $record->detailFixedOther;

   ?>
   <tr>
     <td style="min-width: 35px; border-left: 1px solid #ddd; background-color: #fff;"><?php echo $i?></td>
     <td class="td-style" style="background-color: #fff; text-align: right;"><?php echo $record->status_name ?></td>
     <td class="td-style" style="background-color: #fff; text-align: right;"><?php echo $record->branch_user_name;?></td>
     <td class="td-style"><?php echo $record->branch_name;?></td>
     <td class="td-style"><?php echo $record->trackID ?></td>
     <td class="td-style"><?php echo $record->orderIDShow ?></td>
     <td class="td-style"><?php echo $data_detailAgent;?></td>
     <td class="td-style"><?php echo $record->customerFullname ?></td>
     <td class="td-style"><?php echo $record->customerTel ?></td>
     <td style="max-width: 240px; min-width: 240px;"><?php echo $record->customerEmail ?></td>
     <td class="td-style"><?php echo $XX ?></td>
     <td class="td-style"><?php echo $brand_details ?></td>
     <td class="td-style"><?php echo $type_details ?></td>
     <td class="td-style"><?php echo $detailSKUName ?></td>
     <td class="td-style"><?php echo $detailNumberWaranty ?></td>
      <td class="td-style"><?php echo $detailEquipment ?></td>
    <td class="td-style"><?php echo $detailNote ?></td>
    <td  class="td-style">
      <?php
      if(!empty($Condition))
      {
        $cx=1;
          foreach ($Condition as $cl)
          {
             $mystring = $detailCondition;
             $findme   = $cl->condition_id;
             $pos = strpos($mystring, $findme);
             //$key = strpos($cl->condition_id, $detailCondition);
             if($pos !== false){
               $select='checked="checked"';
               ?>
               <label class="custom-form">
            <input type="checkbox" id="condition[]" name="condition[]" value="<?php echo $cl->condition_id ?>" <?php echo $select;?>>
                   <span class="label-text"><?php echo $cl->condition_details ?></span>
            </label>
               <?php
             }else{
               $select='';
             }

            $cx++;
          }

          ?>
          <?php
          if($detailConditionOther){
            $data_etc='checked="checked"';
          ?>
          <label class="custom-form">

              <span class="label-text"><BR>อื่นๆ</span>
           </label>
           <?php echo $detailConditionOther;?>
          <?php
          }else{
            $data_etc='';
          }

      }
      ?>
    </td>
    <td>
      <?php
      if(!empty($Estimateprice))
      {
        $ex=1;
          foreach ($Estimateprice as $ep)
          {
             $mystring = $detailEstimatePrice;
             $findme   = $ep->estimateprice_id;
             $pos = strpos($mystring, $findme);
             //$key = strpos($cl->condition_id, $detailCondition);
             if($pos !== false){
               $select='checked="checked"';
              ?>
              <label class="custom-form">
                  <input type="checkbox" id="estimateprice[]" name="estimateprice[]" value="<?php echo $ep->estimateprice_id; ?>" <?php echo $select;?>>
                  <span class="label-text"><?php echo $ep->estimateprice_details; ?></span>
               </label>
              <?php
             }else{
               $select='';
             }

            $ex++;
          }
          ?>

             <?php
             if($detailEstimatePriceOther){
               $data_etc='checked="checked"';
              ?>
              <span class="label-text">อื่นๆ</span>
           </label>
           <?php echo $detailEstimatePriceOther;?>
              <?php
             }else{
               $data_etc='';
             }

      }
      ?>
    </td>

    <td>
      <?php
      if(!empty($Fixed))
      {
        $fx=1;
          foreach ($Fixed as $fl)
          {
             $mystring = $detailFixed;
             $findme   = $fl->fixed_id;
             $pos = strpos($mystring, $findme);
             //$key = strpos($cl->condition_id, $detailCondition);
             if($pos !== false){
               $select='checked="checked"';
               ?>
               <label class="custom-form form-fixed">
                  <input type="checkbox" id="fixed[]" name="fixed[]" value="<?php echo $fl->fixed_id; ?>" <?php echo $select;?> >
                  <span class="label-text"><?php echo $fl->fixed_details; ?></span>
               </label>
               <?php
             }else{
               $select='';
             }
              ?>

              <?php
            $fx++;
          }
          ?>

            <?php
            if($detailFixedOther){
              $data_etc='checked="checked"';
           ?>
           <span class="label-text">อื่นๆ</span>
           </label>
           <?php echo $detailFixedOther;?>
           <?php
            }else{
              $data_etc='';
            }

      }
      ?>
   </td>
     <td class="td-style"><?php echo $date_repair ?></td>
     <td class="td-style"><?php echo $date_update_status ?></td>
     <td class="td-style"><?php echo $date_deliver ?></td>
     <td class="td-style"><?php echo $date_complete ?></td>
     <td class="td-style"><?php
     if($record->RepairPrice){
       echo number_format($record->RepairPrice,0);
     }
     ?></td>
     <td class="td-style"><?php echo $record->waranty_cmg ?></td>

   </tr>
   <?php

       }
   }
   ?>
 </table>
