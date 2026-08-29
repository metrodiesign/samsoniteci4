<?php
$date = date("Y-m-d H:i:s");
$timestamp = strtotime($date);
$strExcelFileName = "Rating_Report_" . $timestamp . ".xls";
header("Content-Type: application/x-msexcel; name=\"$strExcelFileName\"");
header("Content-Disposition: inline; filename=\"$strExcelFileName\"");
header("Pragma:no-cache");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<body>
  <strong>Rating Report</strong>
  <br>
  <br>
  <div id="SiXhEaD_Excel" align=center x:publishsource="Excel">
    <table x:str border=1 cellpadding=0 cellspacing=1 width=100% style="border-collapse:collapse">
      <tr>
        <td align="center" valign="top"><strong>No</strong></td>
        <td valign="top"><strong>Track Id</strong></td>
        <td valign="top"><strong>Order Id</strong></td>
        <td valign="top"><strong>Branch Name</strong></td>
        <td valign="top"><strong>Full Name</strong></td>
        <td valign="top"><strong>Telephone</strong></td>
        <td valign="top"><strong>Email</strong></td>
        <td valign="top"><strong>Request At</strong></td>
        <td valign="top"><strong>Repair At</strong></td>
        <td valign="top"><strong>Complete At</strong></td>
        <td valign="top"><strong>Total Day</strong></td>
        <td valign="top"><strong>CMG Total Day</strong></td>
        <td valign="top"><strong>1. บริการของเจ้าหน้าที่</strong></td>
        <td valign="top"><strong>2. บริการของศูนย์บริการ</strong></td>
        <td valign="top"><strong>3. คุณภาพงานซ่อม</strong></td>
        <td valign="top"><strong>4. ระยะในการซ่อม</strong></td>
        <td valign="top"><strong>5.1 ระยะเวลาซ่อม</strong></td>
        <td valign="top"><strong>5.2 ค่าบริการซ่อม</strong></td>
        <td valign="top"><strong>5.3 คุณภาพงานซ่อม</strong></td>
        <td valign="top"><strong>5.4 ความพึงพอใจในการบริการ</strong></td>
        <td valign="top"><strong>6. ข้อเสนอแนะเพิ่มเติม</strong></td>
      </tr>
      <?php if (!empty($ratings)) { ?>
        <?php $ratingRow = 1; ?>
        <?php foreach ($ratings as $ratingKey => $ratingValue) { ?>
          <tr>
            <td align="center" valign="top"><?php echo $ratingRow; ?></td>
            <td valign="top"><?php echo $ratingValue['trackId']; ?></td>
            <td valign="top"><?php echo $ratingValue['orderId']; ?></td>
            <td valign="top"><?php echo $ratingValue['branchName']; ?></td>
            <td valign="top"><?php echo $ratingValue['fullName']; ?></td>
            <td valign="top"><?php echo $ratingValue['telephone']; ?></td>
            <td valign="top"><?php echo $ratingValue['email']; ?></td>
            <td valign="top"><?php echo $ratingValue['requestAt']; ?></td>
            <td valign="top"><?php echo $ratingValue['repairAt']; ?></td>
            <td valign="top"><?php echo $ratingValue['completeAt']; ?></td>
            <td valign="top"><?php echo $ratingValue['totalDay']; ?></td>
            <td valign="top"><?php echo $ratingValue['CMGTotalDay']; ?></td>
            <td valign="top"><?php echo (isset($ratingValue['ratingScore'][1]) ? $ratingValue['ratingScore'][1] : 0); ?></td>
            <td valign="top"><?php echo (isset($ratingValue['ratingScore'][2]) ? $ratingValue['ratingScore'][2] : 0); ?></td>
            <td valign="top"><?php echo (isset($ratingValue['ratingScore'][3]) ? $ratingValue['ratingScore'][3] : 0); ?></td>
            <td valign="top"><?php echo (isset($ratingValue['ratingScore'][4]) ? $ratingValue['ratingScore'][4] : 0); ?></td>
            <td valign="top"><?php echo (isset($ratingValue['ratingScore'][5]) ? $ratingValue['ratingScore'][5] : 0); ?></td>
            <td valign="top"><?php echo (isset($ratingValue['ratingScore'][5]) ? $ratingValue['ratingScore'][6] : 0); ?></td>
            <td valign="top"><?php echo (isset($ratingValue['ratingScore'][5]) ? $ratingValue['ratingScore'][7] : 0); ?></td>
            <td valign="top"><?php echo (isset($ratingValue['ratingScore'][5]) ? $ratingValue['ratingScore'][8] : 0); ?></td>
            <td valign="top"><?php echo (isset($ratingValue['ratingComment']) ? $ratingValue['ratingComment'] : ''); ?></td>
          </tr>
          <?php $ratingRow++; ?>
        <?php } ?>
      <?php } ?>
    </table>
  </div>
</body>