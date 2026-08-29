<?php
$data_requestDate = "";
$data_detailDatePurchase = "";
$branch_type =  "";
$branch_id =  "";
$bookID =  "";
$detailAgent =  "";
$customerFullname =  "";
$email =  "";
$customerTel =  "";
$detailTypeId =  "";
$detailBrandId = "";
$customerTelTwo =  "";
$detailNote =  "";
$warantyType =  "";
$detailSKUName =  "";
$detailEquipment = "";
$detailNumberWaranty =  "";
$detailConditionOther =  "";
$detailEstimatePriceOther =  "";
$detailFixedOther =  "";
$noti =  "";
$Preefix_branch = "";
$Preefix_book = "";
$numberID = "";
$trackID = "";
$orderID = "";
$request_id = "";
$detailCondition = "";
$detailEstimatePrice = "";
$detailFixed = "";
$detailImage = "";
$action_status = 0;
$create_by_user = "";
if (!empty($OrdersInfo)) {
  foreach ($OrdersInfo as $uf) {
    $request_id = $uf->request_id;
    $requestDate =  $uf->requestDate;
    $dd = $requestDate;
    $AA = substr($dd, 0, 4);
    $BB = substr($dd, 5, 2);
    $CC = substr($dd, 8, 2);
    $DD = $AA + 543;
    $data_requestDate = $CC . "/" . $BB . "/" . $AA;
    $detailDatePurchase =  $uf->detailDatePurchase;
    $pdd = $detailDatePurchase;
    $pAA = substr($pdd, 0, 4);
    $pBB = substr($pdd, 5, 2);
    $pCC = substr($pdd, 8, 2);
    $pDD = $pAA + 543;
    $data_detailDatePurchase = $pCC . "/" . $pBB . "/" . $pAA;
    $branch_type =  $uf->branch_type_id;
    $branch_id =  $uf->branchID;
    $bookID =  $uf->bookID;
    $detailAgent =  $uf->detailAgent;
    $customerFullname =  $uf->customerFullname;
    $email =  $uf->customerEmail;
    $customerTel =  $uf->customerTel;
    $detailTypeId =  $uf->detailTypeId;
    $detailBrandId =  $uf->detailBrandId;
    $customerTelTwo =  $uf->customerTel2;
    $detailNote =  $uf->detailNote;
    $warantyType =  $uf->warantyType;
    $detailSKUName =  $uf->detailSKUName;
    $detailNumberWaranty =  $uf->detailNumberWaranty;
    $detailConditionOther =  $uf->detailConditionOther;
    $detailEstimatePriceOther =  $uf->detailEstimatePriceOther;
    $detailFixedOther =  $uf->detailFixedOther;
    $noti =  $uf->customer_noti;
    $detailEquipment = $uf->detailEquipment;
    $numberID = $uf->numberID;
    $trackID = $uf->trackID;
    $orderID = $uf->orderIDShow;
    $detailCondition = $uf->detailCondition;
    $detailEstimatePrice = $uf->detailEstimatePrice;
    $detailFixed = $uf->detailFixed;
    $detailImage = $uf->detailImage;
    $action_status = $uf->action_status;
    $create_by_user = $uf->create_by_user;
    if ($warantyType == 1) {
      $warantyType_checked = "checked";
    } else {
      $warantyType_checked = "";
    }
    if ($warantyType == 0) {
      $warantyType_none = "checked";
    } else {
      $warantyType_none = "";
    }
    $BranchName = $this->request_order_model->getBranchName($branch_id);
  }
}

?>


<!DOCTYPE html>


<html>


<head>


  <meta charset="utf-8">


  <title>PDF FORM</title>


  <style type="text/css" media="print">
    @page {


      size: A4;
      /* auto is the initial value */


      margin: 0 10mm;
      /* this affects the margin in the printer settings */


    }


    @media print {


      .sheet {


        margin: 0;


        overflow: hidden;


        position: relative;


        box-sizing: border-box;


        page-break-after: always;


      }


      body.A4 .sheet {
        width: 210mm;
        height: 326mm;
        font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
      }


      .sheet.padding-10mm {
        padding: 10mm 0 0 0
      }


      .size-print {
        width: 100%;
      }


      body {
        font-size: 14px;
      }


      .detail td {
        font-size: 14px;
        font-weight: 400;
        padding: 10px 0;
      }


      .detail div {
        width: 45%;
        display: inline-block;
      }


      p {
        margin: 0;
      }


      section {


        width: 100%;
        max-width: 1170px;
        margin: 0 auto;
        position: relative;


      }


    }
  </style>


  <style media="screen">
    section {


      width: 100%;
      max-width: 1170px;
      margin: 0 auto;
      position: relative;


    }


    p {
      margin: 0;
    }


    .detail td {
      font-size: 14px;
      font-weight: 400;
      padding: 10px 0;
    }


    .detail div {
      width: 45%;
      display: inline-block;
    }
  </style>


</head>





<body class="A4">


  <section class="sheet padding-10mm">
    <script language="javascript">
      function printpr()


      {


        window.print()


      }
    </script>


    <input name="btnPrint" type="button" id="btnPrint" value="Print" onClick="JavaScript:this.style.display='none';printpr();">


    <div class="size-print">


      <table style="width: 100%; text-align: left;">


        <th style="width: 100%;">


          <h1 style="line-height: 1; margin-bottom: 25px; text-align:right; margin-right: 20%; color: #014c8f;">ใบรับซ่อม</h1>


        </th>


        <tr>


          <td style="width: 50%;">


            <h2 style="font-size: 18px; line-height: 1; color: #014c8f; margin-bottom: 5px; margin-top: 5px;">บริษัท แซมโซไนท์ (ประเทศไทย) จำกัด<br>SAMSONITE (THAILAND) CO., LTD<BR>สาขา <?php echo $BranchName; ?></h2>


          </td>


          <td style="width: 50%;">


            <!-- <h2 style="line-height: 1; margin-bottom: 0px;"> -->


            <div class="">


              <img src="<?php echo base_url(); ?>assets/images/print-logo.jpg" alt="" style="max-width: 230px; height: auto; width: 230px;">


            </div>





            <!-- </h2> -->


          </td>





        </tr>


      </table><BR>


      <table style="width: 100%; text-align: left; line-height: 1.5;">

        <?php if ($detailAgent == "1") { ?>
          <tr>


            <td colspan="3">


              <strong style="color: red; text-decoration: underline;">URGENT/ซ่อมด่วน</strong>


              <?php





              /*if($detailAgent=="1"){


                      echo "(ใช่)";


                    }else{


                      echo "(ไม่)";


                    }*/


              ?>


            </td>





          </tr>
        <?php } ?>

        <tr>


          <td>


            <strong>TRACK ID/เลขติดตาม</strong>


          </td>


          <td>


            <strong>ORDER ID/เลขส่งซ่อมสินค้า</strong>


          </td>


          <td>


            <strong>REQUEST DATE/วันที่ส่งซ่อม</strong>


          </td>


        </tr>


        <tr>


          <td>


            <?php echo $trackID; ?>


          </td>


          <td>


            <?php echo $orderID; ?>


          </td>


          <td>


            <?php echo $data_requestDate; ?>


          </td>


        </tr>


        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
        <tr>


          <td>


            <strong>FULLNAME/ชื่อลูกค้า</strong>


          </td>


          <td>


            <strong>EMAIL/อีเมล์ลูกค้า</strong>


          </td>


          <td>


            <strong>TEL/เบอร์โทรลูกค้า</strong>


          </td>


        </tr>


        <tr>


          <td>


            <?php echo $customerFullname; ?>


          </td>


          <td>


            <?php echo $email; ?>


          </td>


          <td>


            <?php echo $customerTel; ?>


          </td>


        </tr>


        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
        <tr>


          <td>


            <strong>TEL2/เบอร์โทรศัพท์ลูกค้า2</strong>


          </td>


          <td>


            <strong>PURCHASED DATE/วันที่ซื้อ</strong>


          </td>


          <td>


            <strong>CATEGORY/ประเภท</strong>


          </td>


        </tr>





        <tr>


          <td>


            <?php echo $customerTelTwo; ?>


          </td>


          <td>


            <?php echo $data_detailDatePurchase; ?>


          </td>


          <td>


            <?php


            if (!empty($Producttype)) {


              foreach ($Producttype as $ptl) {


                if ($ptl->type_id == $detailTypeId) {


                  echo $ptl->type_details;
                }
              }
            }


            ?>


          </td>


        </tr>


        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
        <tr>


          <td>


            <strong>BRAND/ยี่ห้อ</strong>


          </td>


          <td>


            <strong>SKU NAME/ชื่อสินค้า</strong>


          </td>


          <td>


            <strong>WARANTY TYPE/ประเภทการรับประกัน</strong>


          </td>


        </tr>


        <tr>


          <td>


            <?php


            if (!empty($Brand)) {


              foreach ($Brand as $bl) {


                if ($bl->brand_id == $detailBrandId) {


                  echo $bl->brand_details;
                }
              }
            }


            ?>


          </td>


          <td>


            <?php echo $detailSKUName; ?>


          </td>


          <td>


            <?php


            if ($detailNumberWaranty) {


              echo "มี " . $detailNumberWaranty;
            } else {


              echo "ไม่มี";
            }


            ?>





          </td>


        </tr>


        <tr>
          <td>&nbsp;</td>
        </tr>
        <tr>


          <td>


            <strong>CONDITION/อาการที่ส่งซ่อม</strong>


          </td>





        </tr>


        <tr>


          <td colspan="3">


            <?php


            if (!empty($Condition)) {
              $mystring = explode('|', $detailCondition);

              $cx = 1;


              foreach ($Condition as $cl) {



                $findme   = $cl->condition_id;

                $pos = in_array($findme, $mystring);

                //$key = strpos($cl->condition_id, $detailCondition);


                if ($pos !== false) {


                  $select = 'checked="checked"';
                } else {


                  $select = '';
                }


            ?>


                <label class="custom-form">


                  <input type="checkbox" id="condition[]" name="condition[]" value="<?php echo $cl->condition_id ?>" disabled <?php echo $select; ?>>


                  <span class="label-text"><?php echo $cl->condition_details ?></span>


                </label>








              <?php


                $cx++;
              }


              ?>





              <label class="custom-form">


                <?php


                if ($detailConditionOther) {


                  $data_etc = 'checked="checked"';
                } else {


                  $data_etc = '';
                }


                ?>


                <input type="checkbox" class="form-check-input" id="condition_etc" name="condition_etc" value="condition_etc" disabled <?php echo $data_etc; ?>>


                <span class="label-text">อื่นๆ</span>


              </label>





              <?php echo $detailConditionOther; ?>


            <?php


            }


            ?>


          </td>





        </tr>


        <tr>
          <td>&nbsp;</td>
        </tr>
        <tr>


          <td>


            <strong>ESTIMATE PRICE/ประเมินราคาส่งซ่อม</strong>


          </td>





        </tr>


        <tr>


          <td colspan="3">


            <?php


            if (!empty($Estimateprice)) {


              $ex = 1;


              foreach ($Estimateprice as $ep) {


                $mystring = $detailEstimatePrice;


                $findme   = $ep->estimateprice_id;


                $pos = strpos($mystring, $findme);


                //$key = strpos($cl->condition_id, $detailCondition);


                if ($pos !== false) {


                  $select = 'checked="checked"';
                } else {


                  $select = '';
                }


            ?>


                <label class="custom-form">


                  <input type="checkbox" id="estimateprice[]" name="estimateprice[]" value="<?php echo $ep->estimateprice_id; ?>" disabled <?php echo $select; ?>>


                  <span class="label-text"><?php echo $ep->estimateprice_details; ?></span>


                </label>


              <?php


                $ex++;
              }


              ?>





              <label class="custom-form">


                <?php


                if ($detailEstimatePriceOther) {


                  $data_etc = 'checked="checked"';
                } else {


                  $data_etc = '';
                }


                ?>


                <input type="checkbox" id="estimateprice_etc" name="estimateprice_etc" value="estimateprice_etc" disabled <?php echo $data_etc ?>>


                <span class="label-text">อื่นๆ</span>


              </label>


              <?php echo $detailEstimatePriceOther; ?>


            <?php


            }


            ?>


          </td>





        </tr>





        <tr>
          <td>&nbsp;</td>
        </tr>
        <tr>


          <td>


            <strong>FIXED/สภาพ,ตำหนิ</strong>


          </td>





        </tr>


        <tr>


          <td colspan="3">


            <?php


            if (!empty($Fixed)) {


              $fx = 1;


              foreach ($Fixed as $fl) {


                $mystring = $detailFixed;


                $findme   = $fl->fixed_id;


                $pos = strpos($mystring, $findme);


                //$key = strpos($cl->condition_id, $detailCondition);


                if ($pos !== false) {


                  $select = 'checked="checked"';
                } else {


                  $select = '';
                }


            ?>


                <label class="custom-form">


                  <input type="checkbox" id="fixed[]" name="fixed[]" value="<?php echo $fl->fixed_id; ?>" disabled <?php echo $select; ?>>


                  <span class="label-text"><?php echo $fl->fixed_details; ?></span>


                </label>


              <?php


                $fx++;
              }


              ?>





              <label class="custom-form">


                <?php


                if ($detailFixedOther) {


                  $data_etc = 'checked="checked"';
                } else {


                  $data_etc = '';
                }


                ?>


                <input type="checkbox" id="fixed_etc" name="fixed_etc" value="fixed_etc" disabled <?php echo $data_etc ?>>


                <span class="label-text">อื่นๆ</span>


              </label>


              <?php echo $detailFixedOther; ?>


            <?php


            }


            ?>


          </td>





        </tr>


        <tr>
          <td>&nbsp;</td>
        </tr>
        <tr>


          <td>


            <strong>EQUIPMENT/อุปกรณ์ที่มาพร้อมกับสินค้า</strong>


          </td>


        </tr>


        <tr>


          <td colspan="3">


            <?php echo $detailEquipment; ?>


          </td>


        </tr>


        <tr>
          <td>&nbsp;</td>
        </tr>
        <tr>


          <td>


            <strong>NOTE/หมายเหตุ</strong>


          </td>


        </tr>


        <tr>


          <td colspan="3">


            <?php echo $detailNote; ?>


          </td>


        </tr>

        <tr>


          <td>


            <strong>CREATED BY/พนักงานผู้รับสินค้า</strong>


          </td>


        </tr>


        <tr>


          <td colspan="3">


            <?php echo $create_by_user; ?>


          </td>


        </tr>

        <tr>
          <td>&nbsp;</td>
        </tr>
        <tr>


          <td>


            <strong>รูป</strong>


          </td>


        </tr>


        <tr>


          <td colspan="3">


            <?php


            //  var_dump($detailImage);


            $temp_images = explode("|", $detailImage);


            if (!empty($temp_images) and ($temp_images != NULL)) {


              for ($xi = 0; $xi < count($temp_images); $xi++) {


                if ($temp_images[$xi]) {


                  echo '&nbsp;&nbsp;<img src="' . base_url() . "uploads/" . $temp_images[$xi] . '" style="width:150px;height:150px">';
                }
              }
            }


            ?>


          </td>


        </tr>


      </table>


      <br>


      <div style="display: -webkit-box; padding: 20px 0;">


        <div style="width: 100%; line-height: 1.5; font-weight: 400;">


          <address style="font-style: Normal;">


            98 อาคารสาทร สแควร์ ออฟฟิศ ทาวเวอร์ ชั้น 37 ห้องเลขที่ 3705-3706 ถนนสาทรเหนือ แขวงสีลม เขตบางรัก กรุงเทพฯ 10500<br>


            98 Sathorn Square Office Tower 37<sup>th</sup> floor room no.3705-3706, North Sathorn Road, Silom, Bangkok, Bangkok 10500


            <p>Tel. (66) 2761-9999


              <span>Fax: (66) 2761-9900</span>


            </p>


          </address>


        </div>


        <div>





        </div>


      </div>


    </div>


  </section>





</body>


</html>