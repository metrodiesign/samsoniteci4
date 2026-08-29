<style>
  .p-0 {
    padding: 0;
  }

  .pt-0 {
    padding-top: 0;
  }

  .pb-10px {
    padding-bottom: 10px;
  }

  .px-0 {
    padding-left: 0;
    padding-right: 0;
  }

  .pl-5px {
    padding-left: 5px;
  }

  .mr-10px {
    margin-right: 10px;
  }

  .mb-15px {
    margin-bottom: 15px;
  }

  .white-space {
    white-space: initial !important;
    box-sizing: border-box;
  }

  .d-block {
    display: block;
  }

  .form-group label {
    text-transform: inherit;
  }

  .multiselect-native-select .btn-default {
    font-size: 14px;
    border-radius: 0;
  }

  .multiselect-native-select {
    width: 100%;
    display: block;
  }

  .multiselect-native-select .btn-group,
  .multiselect-native-select .dropdown-toggle,
  .multiselect-native-select .dropdown-menu {
    width: 100%;
  }

  .multiselect-native-select select {
    width: 100%;
    box-sizing: border-box;
  }

  .searchList {
    font-size: 14px !important;
  }

  @media (max-width: 1199px) {
    .box-saerch {
      display: block;
      float: initial !important;
    }
  }

  @media (max-width: 991px) {
    .d-none {
      display: none;
    }
  }

  @media (min-width: 992px) {
    .form-col {
      max-width: 240px;
    }
  }

  div.dataTables_scrollBody::-webkit-scrollbar {
    width: 15px;
    height: 13px;
    background-color: #4c577d08;
    border-top: 0;
  }

  div.dataTables_scrollBody::-webkit-scrollbar-thumb {
    background-color: #8aa4af;
    border-radius: 0;
  }

  div.dataTables_scrollHead,
  .DTFC_LeftHeadWrapper,
  .DTFC_RightHeadWrapper {
    border-bottom: 2px solid #f4f4f4 !important;
  }

  div.dataTables_scrollBody {
    scrollbar-width: auto !important;
    scrollbar-color: initial !important;
    border-bottom: 1px solid #eff2f5 !important;
  }

  div.DTFC_ScrollWrapper {
    border: 1px solid #eff2f5 !important;
    margin-bottom: 10px;
  }

  .DTFC_LeftBodyLiner {
    overflow-y: hidden !important;
    overflow-x: hidden !important;
    width: 100% !important;
  }

  .DTFC_RightBodyLiner {
    overflow-y: hidden !important;
    overflow-x: hidden !important;
    width: inherit !important;
  }

  .DTFC_LeftHeadWrapper,
  .DTFC_LeftBodyWrapper {
    border-right: 1px solid #ededed !important;
  }

  .DTFC_RightHeadWrapper,
  .DTFC_RightBodyWrapper {
    border-left: 1px solid #ededed !important;
  }

  table.dataTable td:first-child,
  table.dataTable th:first-child {
    border-left: 0 !important;
  }

  table.dataTable td:last-child,
  table.dataTable th:last-child {
    border-right: 0 !important;
  }

  table.dataTable thead th,
  table.dataTable thead td {
    padding-left: 10px;
    padding-right: 20px;
    vertical-align: top !important;
    white-space: nowrap;
    border-bottom: 0;
  }

  table.dataTable tbody th,
  table.dataTable tbody td {
    vertical-align: top !important;
    white-space: nowrap;
    max-width: 260px !important;

  }

  div.dataTables_wrapper {
    margin: 0 auto;
  }

  table.dataTable tr:first-child td {
    border-top: 0 !important;
  }

  table.dataTable tr:last-child td {
    border-bottom: 0 !important;
  }
</style>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/bootstrap-multiselect.css" type="text/css">
<script type="text/javascript" src="<?php echo base_url(); ?>assets/dist/js/bootstrap-multiselect.js"></script>
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
  <div class="content-table-form">
    <section class="content-header">
      <div class="row">
        <div class="col-xs-8">
          <h1 class="text-uppercase"><i class="fa fa-link"></i> Report Tracking</h1>
        </div>
        <div class="col-xs-4">
          <div class="pull-right">
            <?php
            if ($companny_id == "") {
              $data_companny_id = 0;
            } else {
              $data_companny_id = $companny_id;
            }

            if ($sdate == "") {
              $data_sdate = 0;
            } else {
              $data_sdate = str_replace('/', '-', $sdate);
            }

            if ($edate == "") {
              $data_edate = 0;
            } else {
              $data_edate = str_replace('/', '-', $edate);
            }

            if ($data_status_id == "") {
              $xdata_status_id = 0;
            } else {
              $xdata_status_id = $data_status_id;
            }
            ?>

            <a class="btn btn-primary" href="<?php echo base_url(); ?>order/excel_report/<?php echo $data_companny_id; ?>/<?php echo $data_sdate; ?>/<?php echo $data_edate; ?>/<?php echo urlencode($xdata_status_id); ?>/<?php echo $searchText; ?>">Export</a>
          </div>
        </div>
      </div>
    </section>
    <section class="content">
      <div class="row">
        <div class="col-xs-12">
          <div class="box">
            <div style="margin-bottom: 10px;" class="box-header p-0">
              <div class="row">
                <div class="col-xs-12">
                  <?php if (!empty($companny_id)) { ?>
                    <form action="<?php echo base_url() ?>ReportTrackingListing/0/<?php echo $companny_id; ?>" method="POST" id="searchList">
                    <?php } else { ?>
                      <form action="<?php echo base_url() ?>ReportTrackingListing" method="POST" id="searchList">
                      <?php } ?>
                      <div class="row">
                        <div class="col-xs-12 col-md-4 form-col">
                          <div class="form-group">
                            <label class="d-block">Status:</label>
                            <select id="t_status_id" name="t_status_id" style="width: 100%;" class="form-control" multiple>
                              <?php if (!empty($Status)) { ?>
                                <?php unset($Status[5]); ?>
                                <?php foreach ($Status as $rl) { ?>
                                  <option value="<?php echo $rl->status_id; ?>" <?php echo (in_array($rl->status_id, $selected_status_id) ? 'selected' : ''); ?>><?php echo $rl->status_name; ?></option>
                                <?php } ?>
                              <?php } ?>
                            </select>
                            <input type="hidden" id="status_id" name="status_id">
                          </div>
                        </div>
                        <div class="col-xs-12 col-md-3 form-col">
                          <div class="form-group">
                            <label class="d-block">From Date:</label>
                            <input type="text" id="sdate" name="sdate" value="<?php echo $sdate; ?>" class="form-control" placeholder="Date" autocomplete="off">
                          </div>
                        </div>
                        <div class="col-xs-12 col-md-3 form-col">
                          <div class="form-group">
                            <label class="d-block">To Date:</label>
                            <input type="text" id="edate" name="edate" value="<?php echo $edate; ?>" class="form-control" placeholder="Date" autocomplete="off">
                          </div>
                        </div>
                        <div class="col-xs-12 col-md-2">
                          <div class="form-group">
                            <label class="d-block d-none">&nbsp;</label>
                            <input type="hidden" name="searchText" value="<?php echo $searchText; ?>" class="form-control input-sm pull-right input-saerch" placeholder="Search" />
                            <button type="submit" class="btn btn-default searchList"><i class="fa fa-search"></i></button>
                          </div>
                        </div>
                      </div>
                      </form>
                </div>
              </div>
            </div>

            <div style="width: 100%; display: block;" class="box-body no-padding">
              <table id="examples" class="table table-striped" cellspacing="0" width="100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Action Status</th>
                    <th>Status Update</th>
                    <th>TotalDay</th>
                    <?php
                    if (empty($BranchID)) {
                      echo '<th>CMG TotalDay</th>';
                    }
                    ?>
                    <th>Branch User</th>
                    <th>Branch Name</th>
                    <th>trackID</th>
                    <th>orderID</th>
                    <th>เลขที่ CMG</th>
                    <th>Urgent</th>
                    <th>Fullname</th>
                    <th>Tel</th>
                    <th>Email</th>
                    <th>RequestDate</th>
                    <th>รับเข้า</th>
                    <th>อัพเดทล่าสุด</th>
                    <th>ศูนย์ส่งคืนสาขา</th>
                    <th>ลูกค้ามารับคืน</th>
                    <th>Logistics</th>
                    <th>ราคาซ่อม</th>
                    <th>Warannty</th>
                    <th style="min-width: 120px; max-width: 120px; width: 120px;" class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  if (!empty($OrdersRecords)) {
                    if ($page == 0) {
                      $i = 0;
                    } else {
                      $i = $page;
                    }
                    $detailAgent =  "";
                    foreach ($OrdersRecords as $record) {
                      $i++;
                      $dd = $record->requestDate;
                      $AA = substr($dd, 0, 4);
                      $BB = substr($dd, 5, 2);
                      $CC = substr($dd, 8, 2);
                      $DD = $AA;
                      $XX = $CC . "/" . $BB . "/" . $DD;
                      $detailAgent =  $record->detailAgent;
                      if ($detailAgent == 1) {
                        $data_detailAgent = "มี";
                      } else {
                        $data_detailAgent = "ไม่มี";
                      }
                      $repair = $record->date_repair;
                      if ($repair and $repair != null) {
                        $repair_a = substr($repair, 0, 4);
                        $repair_b = substr($repair, 5, 2);
                        $repair_c = substr($repair, 8, 2);
                        $repair_d = $repair_a;
                        $date_repair = $repair_c . "/" . $repair_b . "/" . $repair_d;
                      } else {

                        $date_repair = "";
                      }

                      $update_status = $record->date_update_status;
                      if ($update_status and $update_status != null) {
                        $update_status_a = substr($update_status, 0, 4);
                        $update_status_b = substr($update_status, 5, 2);
                        $update_status_c = substr($update_status, 8, 2);
                        $update_status_d = $update_status_a;
                        $date_update_status = $update_status_c . "/" . $update_status_b . "/" . $update_status_d;
                      } else {
                        $date_update_status = "";
                      }

                      $deliver = $record->date_deliver;
                      if ($deliver and $deliver != null) {
                        $deliver_a = substr($deliver, 0, 4);
                        $deliver_b = substr($deliver, 5, 2);
                        $deliver_c = substr($deliver, 8, 2);
                        $deliver_d = $deliver_a;
                        $date_deliver = $deliver_c . "/" . $deliver_b . "/" . $deliver_d;
                      } else {
                        $date_deliver = "";
                      }
                      $complete = $record->date_complete;
                      if ($complete and $complete != null) {
                        $complete_a = substr($complete, 0, 4);
                        $complete_b = substr($complete, 5, 2);
                        $complete_c = substr($complete, 8, 2);
                        $complete_d = $complete_a;
                        $date_complete = $complete_c . "/" . $complete_b . "/" . $complete_d;
                      } else {
                        $date_complete = "";
                      }
                      if ($record->TotalDay == "") {
                        $data_TotalDay = "";
                      } else {
                        $data_TotalDay = $record->TotalDay + 1;
                      }
                      if ($record->CMGTotalDay == "") {
                        $data_CMGTotalDay = "";
                      } else {
                        $data_CMGTotalDay = $record->CMGTotalDay;
                      }


                      $provider_id = $record->provider_id;
                      $trackID = $record->trackID;
                      $orderID = $record->orderID;
                      $number_cmg = $record->number_cmg;
                      $Telephone = $record->customerTel;
                      $logistics_etc_detail = $record->logistics_etc_detail;
                      if ($logistics_etc_detail != "" and $logistics_etc_detail != NULL) {
                        $data_ProviderName = $logistics_etc_detail;
                      } else {
                        //$data_ProviderName="";
                        $ProviderName = $this->request_order_model->getProviderName($provider_id);
                        // echo $ProviderName;
                        if ($ProviderName == "") {
                          $data_ProviderName = "";
                        } else {
                          $data_ProviderName = $ProviderName;
                        }
                      }
                      $ststus_update = $this->request_order_model->chack_status_update($orderID, $Telephone);
                      $action_status = $record->action_status;
                      $url_print = base_url() . 'OrderPrint/' . $record->request_id;
                  ?>
                      <tr>
                        <td><?php echo $i; ?></td>
                        <td><?php echo $record->status_name; ?></td>
                        <td><?php echo $ststus_update; ?></td>
                        <td><?php echo $data_TotalDay; ?></td>
                        <?php
                        if (empty($BranchID)) {
                          echo '<td>' . $data_CMGTotalDay . '</td>';
                        }
                        ?>
                        <td><?php echo $record->branch_user_name; ?></td>
                        <td><?php echo $record->branch_name; ?></td>
                        <td><?php echo $record->trackID; ?></td>
                        <td><?php echo $record->orderIDShow; ?></td>
                        <td><?php echo $number_cmg; ?></td>
                        <td><?php echo $data_detailAgent; ?></td>
                        <td><?php echo $record->customerFullname; ?></td>
                        <td><?php echo $record->customerTel; ?></td>
                        <td><?php echo $record->customerEmail; ?></td>
                        <td><?php echo $XX; ?></td>
                        <td><?php echo $date_repair; ?></td>
                        <td><?php echo $date_update_status; ?></td>
                        <td><?php echo $date_deliver; ?></td>
                        <td><?php echo $date_complete; ?></td>
                        <td>
                          <?php echo $data_ProviderName; ?>
                        </td>
                        <td>
                          <?php
                          if ($record->RepairPrice) {
                            echo number_format($record->RepairPrice, 0);
                          }
                          ?>
                        </td>
                        <td><?php echo $record->waranty_cmg; ?></td>
                        <td class="text-center">
                          <?php
                          if ($action_status == 1 or $GroupID == 1) {
                          ?>
                            <a class="btn btn-sm btn-info" href="<?php echo base_url() . 'editOrdersOld/' . $record->request_id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
                            <a class="btn btn-sm btn-danger deleteOrders" href="#" data-order_id="<?php echo $record->request_id; ?>" title="Cancel"><i class="fa fa-window-close"></i></a>
                          <?php
                          }
                          ?>
                          <a class="btn btn-sm btn-info" onclick="printPreview('<?php echo $url_print; ?>')" title="Print"><i class="fa fa-print"></i></a>
                        </td>
                      </tr>
                  <?php
                    }
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<script type="text/javascript">
  jQuery(document).ready(function() {
    var b = "<?php echo base_url(); ?>";
    jQuery(document).on("click", ".deleteOrders", function() {
      var orderid = $(this).data("order_id"),
        hitURL = b + "deleteOrders",
        currentRow = $(this);
      //  console.log(orderid+hitURL);
      //alert(branchid);
      var confirmation = confirm("Are you sure to delete this  ? ");

      if (confirmation) {
        jQuery.ajax({
          type: "POST",
          dataType: "json",
          url: hitURL,
          data: {
            orderid: orderid
          }
        }).done(function(data) {
          console.log(data);
          currentRow.parents('tr').remove();
          if (data.status = true) {
            alert("branch successfully deleted");
          } else if (data.status = false) {
            alert("branch deletion failed");
          } else {
            alert("Access denied..!");
          }
        });
      }
    });


    jQuery(document).on("click", ".searchList", function() {
      $(function() {



        var status_id = $('#t_status_id').val();
        $("#status_id").val(status_id);



      });
    });

  });
  jQuery(document).ready(function() {
    jQuery('ul.pagination li a').click(function(e) {
      e.preventDefault();
      var link = jQuery(this).get(0).href;
      var value = link.substring(link.lastIndexOf('/') + 1);
      var xvalue = "<?php echo $companny_id; ?>";
      jQuery("#searchList").attr("action", baseURL + "ReportTrackingListing/" + value + '/' + xvalue);
      jQuery("#searchList").submit();
    });


  });
</script>
<script type="text/javascript">
  function search_status() {
    $(function() {



      alert($('#status_id').val());



    });

    //$( "#searchList" ).submit();
  }
  $(function() {
    var dateBefore = null;
    $("#sdate").datepicker({
      dateFormat: 'dd/mm/yy',
      //showOn: 'button',
      //      buttonImage: 'http://jqueryui.com/demos/datepicker/images/calendar.gif',
      buttonImageOnly: false,
      //  dayNamesMin: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
      //  monthNamesShort: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],
      changeMonth: true,
      changeYear: true,
      beforeShow: function() {
        if ($(this).val() != "") {
          var arrayDate = $(this).val().split("/");
          arrayDate[2] = parseInt(arrayDate[2]);
          $(this).val(arrayDate[0] + "/" + arrayDate[1] + "/" + arrayDate[2]);
        }
        setTimeout(function() {
          $.each($(".ui-datepicker-year option"), function(j, k) {
            var textYear = parseInt($(".ui-datepicker-year option").eq(j).val());
            $(".ui-datepicker-year option").eq(j).text(textYear);
          });
        }, 50);
      },
      onChangeMonthYear: function() {
        setTimeout(function() {
          $.each($(".ui-datepicker-year option"), function(j, k) {
            var textYear = parseInt($(".ui-datepicker-year option").eq(j).val());
            $(".ui-datepicker-year option").eq(j).text(textYear);
          });
        }, 50);
      },
      onClose: function() {
        if ($(this).val() != "" && $(this).val() == dateBefore) {
          var arrayDate = dateBefore.split("/");
          arrayDate[2] = parseInt(arrayDate[2]);
          $(this).val(arrayDate[0] + "/" + arrayDate[1] + "/" + arrayDate[2]);
        }
      },
      onSelect: function(dateText, inst) {
        dateBefore = $(this).val();
        var arrayDate = dateText.split("/");
        arrayDate[2] = parseInt(arrayDate[2]);
        $(this).val(arrayDate[0] + "/" + arrayDate[1] + "/" + arrayDate[2]);
      }

    });

  });
  $(function() {
    var dateBefore = null;
    $("#edate").datepicker({
      dateFormat: 'dd/mm/yy',
      //showOn: 'button',
      //      buttonImage: 'http://jqueryui.com/demos/datepicker/images/calendar.gif',
      buttonImageOnly: false,
      //  dayNamesMin: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
      //  monthNamesShort: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],
      changeMonth: true,
      changeYear: true,
      beforeShow: function() {
        if ($(this).val() != "") {
          var arrayDate = $(this).val().split("/");
          arrayDate[2] = parseInt(arrayDate[2]);
          $(this).val(arrayDate[0] + "/" + arrayDate[1] + "/" + arrayDate[2]);
        }
        setTimeout(function() {
          $.each($(".ui-datepicker-year option"), function(j, k) {
            var textYear = parseInt($(".ui-datepicker-year option").eq(j).val());
            $(".ui-datepicker-year option").eq(j).text(textYear);
          });
        }, 50);
      },
      onChangeMonthYear: function() {
        setTimeout(function() {
          $.each($(".ui-datepicker-year option"), function(j, k) {
            var textYear = parseInt($(".ui-datepicker-year option").eq(j).val());
            $(".ui-datepicker-year option").eq(j).text(textYear);
          });
        }, 50);
      },
      onClose: function() {
        if ($(this).val() != "" && $(this).val() == dateBefore) {
          var arrayDate = dateBefore.split("/");
          arrayDate[2] = parseInt(arrayDate[2]);
          $(this).val(arrayDate[0] + "/" + arrayDate[1] + "/" + arrayDate[2]);
        }
      },
      onSelect: function(dateText, inst) {
        dateBefore = $(this).val();
        var arrayDate = dateText.split("/");
        arrayDate[2] = parseInt(arrayDate[2]);
        $(this).val(arrayDate[0] + "/" + arrayDate[1] + "/" + arrayDate[2]);
      }

    });

  });
</script>
<script type="text/javascript">
  function printPreview(url) {
    var windowWidth = 1000;
    var windowHeight = 600;
    var myleft = (screen.width) ? (screen.width - windowWidth) * 0.5 : 100;
    var mytop = (screen.height) ? (screen.height - windowHeight) * 0.5 : 100;
    var feature = 'left=' + myleft + ',top=' + eval(mytop - 50) + ',width=' + windowWidth + ',height=' + windowHeight + ',';
    feature += 'menubar=yes,status=no,location=no,toolbar=no,scrollbars=yes';
    window.open(url, 'samsonite', feature);

  }
  $(function() {

    $('#t_status_id').multiselect({

      includeSelectAllOption: true

    });

  });
</script>
<script>
  $(document).ready(function() {
    var table = null;
    const scrollY = '60vh';
    const isMobile = window.matchMedia("(min-width: 1200px)").matches;
    const fixedColumns = isMobile ? {
      leftColumns: 3,
      rightColumns: 1
    } : undefined;

    table = $('#examples').DataTable({
      autoWidth: false,
      pageLength: 25,
      fixedHeader: true,
      fixedColumns: fixedColumns,
      scrollCollapse: true,
      scrollX: true,
      scrollY: scrollY,
      initComplete: function(settings, json) {
        setTimeout(() => {
          this.api().columns.adjust().draw();
        }, 1000);
      }
    });
  });
</script>