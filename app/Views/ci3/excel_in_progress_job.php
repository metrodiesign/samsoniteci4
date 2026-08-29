<?php
$date = date("Y-m-d H:i:s");
$timestamp = strtotime($date);
$strExcelFileName = "In_Progress_Report_" . $timestamp . ".xls";
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
  <strong>In Progress Report</strong>
  <br>
  <br>
  <div id="SiXhEaD_Excel" align=center x:publishsource="Excel">
    <table x:str border=1 cellpadding=0 cellspacing=1 width=100% style="border-collapse:collapse">
      <tr>
        <td align="center" valign="top"><strong>No</strong></td>
        <td align="left" valign="top"><strong>Status</strong></td>
        <td align="left" valign="top"><strong>Track Id</strong></td>
        <td align="left" valign="top"><strong>Order Id</strong></td>
        <td align="left" valign="top"><strong>Branch Name</strong></td>
        <td align="left" valign="top"><strong>Full Name</strong></td>
        <td align="left" valign="top"><strong>Telephone</strong></td>
        <td align="left" valign="top"><strong>Request At</strong></td>
        <td align="left" valign="top"><strong>Day</strong></td>
      </tr>
      <?php if (!empty($jobs)) { ?>
        <?php $row = 1; ?>
        <?php foreach ($jobs as $jobKey => $jobValue) { ?>
          <?php $dd = $jobValue->requestDate;
          $AA = substr($dd, 0, 4);
          $BB = substr($dd, 5, 2);
          $CC = substr($dd, 8, 2);
          $DD = $AA;
          $XX = $CC . "/" . $BB . "/" . $DD; ?>

          <tr>
            <td align="center" valign="top"><?php echo $row; ?></td>
            <td align="left" valign="top"><?php echo trim($jobValue->status_name_th); ?></td>
            <td align="left" valign="top"><?php echo trim($jobValue->trackID); ?></td>
            <td align="left" valign="top"><?php echo trim($jobValue->orderIDShow); ?></td>
            <td align="left" valign="top"><?php echo (isset($branchs[$jobValue->branchID]) ? $branchs[$jobValue->branchID] : ''); ?></td>
            <td align="left" valign="top"><?php echo trim($jobValue->customerFullname); ?></td>
            <td align="left" valign="top" style="mso-number-format:'\@';">&#8203;<?php echo trim($jobValue->customerTel); ?></td>
            <td align="left" valign="top"><?php echo trim($XX); ?></td>
            <td align="left" valign="top"><?php echo trim(number_format($jobValue->Total, 0)); ?></td>
          </tr>
          <?php $row++; ?>
        <?php } ?>
      <?php } ?>
    </table>
  </div>
</body>