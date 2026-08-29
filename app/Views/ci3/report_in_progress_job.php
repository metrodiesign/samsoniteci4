<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/bootstrap-multiselect.css" type="text/css">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/fonts/stylesheet.css" type="text/css">

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

  .mw-200px {
    max-width: 200px;
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

<div class="content-wrapper">
  <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
  <div class="content-table-form">
    <section class="content-header">
      <div class="row">
        <div class="col-xs-8">
          <h1 class="text-uppercase"><i class="fa fa-cart-arrow-down"></i> In Progress Report</h1>
        </div>
        <div class="col-xs-4">
          <div class="pull-right">
            <?php
            if ($start_date == '') {
              $data_sdate = '';
            } else {
              $data_sdate = str_replace('/', '-', $start_date);
            }

            if ($end_date == '') {
              $data_edate = '';
            } else {
              $data_edate = str_replace('/', '-', $end_date);
            }
            ?>

            <a class="btn btn-primary" href="<?php echo base_url(); ?>user/excel_in_progress_job?branchId=<?php echo $BranchID; ?>&startDate=<?php echo $data_sdate; ?>&endDate=<?php echo $data_edate; ?>&status=<?php echo urlencode($status_id); ?>">Export</a>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="row">
        <div class="col-md-12">
          <div class="box">
            <div style="margin-bottom: 10px;" class="box-header p-0">
              <div class="row">
                <div class="col-xs-12">
                  <form action="<?php echo base_url(); ?>user/report_in_progress_job" method="POST" id="searchList">
                    <div class="row">
                      <div class="col-xs-12 col-md-4 form-col">
                        <div class="form-group">
                          <label class="d-block">Status:</label>
                          <select id="t_status_id" name="t_status_id" style="width: 100%;" class="form-control" multiple>
                            <?php if (!empty($statuses)) { ?>
                              <?php foreach ($statuses as $rl) { ?>
                                <option value="<?php echo $rl->status_id; ?>" <?php echo (in_array($rl->status_id, $selected_status_id) ? 'selected' : ''); ?>><?php echo trim($rl->status_name); ?></option>
                              <?php } ?>
                            <?php } ?>
                          </select>
                          <input type="hidden" id="status_id" name="status_id" value="<?php echo $status_id; ?>">
                        </div>
                      </div>
                      <div class="col-xs-12 col-md-4 form-col">
                        <div class="form-group">
                          <label class="d-block">Branch:</label>
                          <?php $BID = $this->session->userdata('BranchID'); ?>
                          <?php if ($BID) { ?>
                            <input type="hidden" name="branch_id" value="<?php echo $BID; ?>" id="branch_id" class="form-control" />
                          <?php } else { ?>
                            <select id="branch_id" name="branch_id" class="form-control">
                              <option value="0">ALL</option>
                              <?php if (!empty($brans_list)) { ?>
                                <?php foreach ($brans_list as $rl) { ?>
                                  <option value="<?php echo $rl->branch_id; ?>" <?php echo ($rl->branch_id == set_value('branch_id') ? 'selected' : ''); ?>>
                                    <?php echo $rl->branch_name . ',' . $rl->branch_user_name; ?></option>
                                <?php } ?>
                              <?php } ?>
                            </select>
                          <?php } ?>
                        </div>
                      </div>
                      <div class="col-xs-12 col-md-3 form-col">
                        <div class="form-group">
                          <label class="d-block">From Date:</label>
                          <input type="text" id="start_date" name="start_date" value="<?php echo $start_date; ?>" class="form-control" placeholder="Date" autocomplete="off">
                        </div>
                      </div>
                      <div class="col-xs-12 col-md-3 form-col">
                        <div class="form-group">
                          <label class="d-block">To Date:</label>
                          <input type="text" id="end_date" name="end_date" value="<?php echo $end_date; ?>" class="form-control" placeholder="Date" autocomplete="off">
                        </div>
                      </div>
                      <div class="col-xs-12 col-md-2">
                        <div class="form-group">
                          <label class="d-block d-none">&nbsp;</label>
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
                    <th class="text-center mw-200px">No</th>
                    <th>Status</th>
                    <th>Track Id</th>
                    <th>Order Id</th>
                    <th>Branch Name</th>
                    <th>Full Name</th>
                    <th>Tel</th>
                    <th>Request Date</th>
                    <th>Day</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($jobs)) { ?>
                    <?php $row = 1; ?>
                    <?php foreach ($jobs as $jobKey => $jobValue) {  ?>
                      <?php $dd = $jobValue->requestDate;
                      $AA = substr($dd, 0, 4);
                      $BB = substr($dd, 5, 2);
                      $CC = substr($dd, 8, 2);
                      $DD = $AA;
                      $XX = $CC . "/" . $BB . "/" . $DD; ?>

                      <tr>
                        <td class="text-center mw-200px"><?php echo $row; ?></td>
                        <td><?php echo trim($jobValue->status_name_th); ?></td>
                        <td><?php echo trim($jobValue->trackID); ?></td>
                        <td><?php echo trim($jobValue->orderIDShow); ?></td>
                        <td><?php echo (isset($branchs[$jobValue->branchID]) ? $branchs[$jobValue->branchID] : ''); ?></td>
                        <td><?php echo trim($jobValue->customerFullname); ?></td>
                        <td><?php echo trim($jobValue->customerTel); ?></td>
                        <td><?php echo trim($XX); ?></td>
                        <td><?php echo trim(number_format($jobValue->Total, 0)); ?></td>
                      </tr>
                      <?php $row++; ?>
                    <?php } ?>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<script type="text/javascript" src="<?php echo base_url(); ?>assets/dist/js/bootstrap-multiselect.js"></script>

<script type="text/javascript">
  function search_status() {
    $("#searchList").submit();
  }

  jQuery(document).on("click", ".searchList", function() {
    $(function() {
      var status_id = $('#t_status_id').val();
      $("#status_id").val(status_id);
    });
  });

  $(function() {
    var dateBefore = null;
    $("#start_date").datepicker({
      dateFormat: 'dd/mm/yy',
      //showOn: 'button',
      //      buttonImage: 'http://jqueryui.com/demos/datepicker/images/calendar.gif',
      buttonImageOnly: false,
      //dayNamesMin: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
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
    $("#end_date").datepicker({
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
  $("#btnGuru").click(function() {
    fnExcelReport('examples', 'report_in_progress_average');
  });


  function fnExcelReport(id, name) {
    var tab_text = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    tab_text = tab_text + '<head><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    tab_text = tab_text + '<x:Name>In Progress Report</x:Name>';
    tab_text = tab_text + '<x:WorksheetOptions><x:Panes></x:Panes></x:WorksheetOptions></x:ExcelWorksheet>';
    tab_text = tab_text + '</x:ExcelWorksheets></x:ExcelWorkbook></xml></head><body>';
    tab_text = tab_text + "<table border='1px'>";
    var exportTable = $('#' + id).clone();
    exportTable.find('input').each(function(index, elem) {
      $(elem).remove();
    });
    tab_text = tab_text + exportTable.html();
    tab_text = tab_text + '</table></body></html>';
    var data_type = 'data:application/vnd.ms-excel; charset=UTF-8;base64,';
    var ua = window.navigator.userAgent;
    var msie = ua.indexOf("MSIE ");

    var fileName = name + '_' + parseInt(Math.random() * 10000000000) + '.xls';
    if (msie > 0 || !!navigator.userAgent.match(/Trident.*rv\:11\./)) {
      if (window.navigator.msSaveBlob) {
        var blob = new Blob([tab_text], {
          type: "application/csv;charset=utf-8;"
        });
        navigator.msSaveBlob(blob, fileName);
      }
    } else {
      var blob2 = new Blob([tab_text], {
        type: "application/csv;charset=utf-8;"
      });
      var filename = fileName;
      var elem = window.document.createElement('a');
      elem.href = window.URL.createObjectURL(blob2);
      elem.download = filename;
      document.body.appendChild(elem);
      elem.click();
      document.body.removeChild(elem);
    }
  }
</script>

<script>
  $(document).ready(function() {
    $('#t_status_id').multiselect({
      includeSelectAllOption: true
    });

    var table = null;
    const scrollY = '60vh';
    const isMobile = window.matchMedia("(min-width: 1200px)").matches;
    const fixedColumns = isMobile ? {
      leftColumns: 3,
      rightColumns: 0
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