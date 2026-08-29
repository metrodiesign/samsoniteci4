<link href="<?php echo base_url(); ?>assets/fonts/stylesheet.css" rel="stylesheet">

<style>
  .mb-30 {
    margin-bottom: 30px;
  }

  .pt-0 {
    padding-top: 0;
  }

  .px-0 {
    padding-left: 0;
    padding-right: 0;
  }

  .fa.fa-star {
    color: orange;
  }

  .white-space {
    white-space: initial !important;
    box-sizing: border-box;
  }

  .min-w-260px {
    min-width: 260px !important;
  }

  .d-block {
    display: block;
  }

  .form-group label {
    text-transform: inherit;
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

    .max-w-800px {
      max-width: 800px !important;
    }
  }

  .container-content {
    max-width: 1170px;
  }

  .canvasjs-chart-credit {
    display: none;
  }
</style>

<div class="content-wrapper">
  <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
  <div class="content-table-form container-content">
    <section class="content-header">
      <div class="row">
        <div class="col-xs-8">
          <h1 class="text-uppercase"><i class="fa fa-cart-arrow-down"></i> In Progress Report</h1>
        </div>
        <div class="col-xs-4">
          <div class="pull-right">
            <a id="btnGuru" class="btn btn-primary" href="javascript:void(0);">Export</a>
          </div>
        </div>
      </div>
    </section>
    <section class="content">
      <div class="row">
        <div class="col-md-12">
          <div class="box">
            <div class="box-header pt-0 px-0">
              <div class="row">
                <div class="col-xs-12">
                  <form action="<?php echo base_url(); ?>user/report_in_progress_average" method="POST" id="searchList">
                    <div class="row">
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
                                  <option value="<?php echo $rl->branch_id; ?>" <?php echo ($rl->branch_id == set_value('branch_id') ? 'selected' : ''); ?>><?php echo $rl->branch_name . ',' . $rl->branch_user_name; ?></option>
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
            <div class="row mb-30">
              <div class="col-md-12 col-sm-12 col-xs-12">
                <div id="chartContainer" style="height: 370px; width: 100%;"></div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel tile fixed_height_320">
                  <div class="x_title">
                    <div class="clearfix"></div>
                  </div>
                  <div class="x_content">
                    <div class="box-body table-responsive no-padding">
                      <table id="myDataTable" class="table table-striped table-bordered" style="width:100%">
                        <tr>
                          <th align="center" class="text-center">No</th>
                          <th align="left" class="text-left">Detail</th>
                          <th align="right" class="text-right">Job</th>
                          <th align="right" class="text-right">Average (Percent)</th>
                        </tr>

                        <?php
                        $Total_result_a = ($newStatusTotals + $requestStatusTotals + $repairStatusTotals + $closeStatusTotals + $returnStatusTotals);

                        if ($newStatusTotals > 0) {
                          $Total_p_result_a = (($newStatusTotals * 100) / $Total_result_a);
                        } else {
                          $Total_p_result_a = 0;
                        }

                        if ($requestStatusTotals > 0) {
                          $Total_p_result_b = (($requestStatusTotals * 100) / $Total_result_a);
                        } else {
                          $Total_p_result_b = 0;
                        }

                        if ($repairStatusTotals > 0) {
                          $Total_p_result_c = (($repairStatusTotals * 100) / $Total_result_a);
                        } else {
                          $Total_p_result_c = 0;
                        }

                        if ($closeStatusTotals > 0) {
                          $Total_p_result_d = (($closeStatusTotals * 100) / $Total_result_a);
                        } else {
                          $Total_p_result_d = 0;
                        }

                        if ($returnStatusTotals > 0) {
                          $Total_p_result_e = (($returnStatusTotals * 100) / $Total_result_a);
                        } else {
                          $Total_p_result_e = 0;
                        }

                        $Total_p_result = $Total_p_result_a + $Total_p_result_b + $Total_p_result_c + $Total_p_result_d + $Total_p_result_e;
                        ?>

                        <tr>
                          <td align="center">1</td>
                          <td align="left">เปิดงานซ่อม รอศูนย์บริการมารับ</td>
                          <td align="right"><?php echo number_format($newStatusTotals, 0); ?></td>
                          <td align="right"><?php echo number_format($Total_p_result_a, 2); ?>%</td>
                        </tr>
                        <tr>
                          <td align="center">2</td>
                          <td align="left">สินค้าจัดส่งเข้าศูนย์บริการ</td>
                          <td align="right"><?php echo number_format($requestStatusTotals, 0); ?></td>
                          <td align="right"><?php echo number_format($Total_p_result_b, 2); ?>%</td>
                        </tr>
                        <tr>
                          <td align="center">3</td>
                          <td align="left">อยู่ระหว่างดำเนินการซ่อมสินค้า</td>
                          <td align="right"><?php echo number_format($repairStatusTotals, 0); ?></td>
                          <td align="right"><?php echo number_format($Total_p_result_c, 2); ?>%</td>
                        </tr>
                        <tr>
                          <td align="center">4</td>
                          <td align="left">ซ่อมเสร็จเรียบร้อยแล้ว รอส่งกลับจุดรับบริการ</td>
                          <td align="right"><?php echo number_format($closeStatusTotals, 0); ?></td>
                          <td align="right"><?php echo number_format($Total_p_result_d, 2); ?>%</td>
                        </tr>
                        <tr>
                          <td align="center">5</td>
                          <td align="left">สินค้าถึงจุดรับบริการ รอลูกค้ามารับ</td>
                          <td align="right"><?php echo number_format($returnStatusTotals, 0); ?></td>
                          <td align="right"><?php echo number_format($Total_p_result_e, 2); ?>%</td>
                        </tr>
                        <tr>
                          <td colspan="2" style="font-weight: 700;">TOTAL</td>
                          <td align="right" style="font-weight: 700;"><?php echo number_format($Total_result_a, 0); ?></td>
                          <td align="right" style="font-weight: 700;"><?php echo number_format($Total_p_result, 2); ?>%</td>
                        </tr>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
    </section>
  </div>
</div>

<script src="<?php echo base_url(); ?>assets/plugins/canvasjs-chart-3.8.8/jquery.canvasjs.min.js"></script>

<script type="text/javascript">
  function search_status() {
    $("#searchList").submit();
  }

  $(function() {
    var dateBefore = null;

    $("#start_date").datepicker({
      dateFormat: 'dd/mm/yy',
      buttonImageOnly: false,
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
      buttonImageOnly: false,
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
    fnExcelReport('myDataTable', 'report_in_progress_average');
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
    var data_type = 'data:application/vnd.ms-excel';
    var ua = window.navigator.userAgent;
    var msie = ua.indexOf("MSIE ");

    var fileName = name + '_' + parseInt(Math.random() * 10000000000) + '.xls';
    if (msie > 0 || !!navigator.userAgent.match(/Trident.*rv\:11\./)) {
      if (window.navigator.msSaveBlob) {
        var blob = new Blob([tab_text], {
          type: "application/csv;charset=utf-8;charset=tis-620"
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
  window.onload = function() {
    var t1 = "<?php echo round($Total_p_result_a, 2); ?>";
    var t2 = "<?php echo round($Total_p_result_b, 2); ?>";
    var t3 = "<?php echo round($Total_p_result_c, 2); ?>";
    var t4 = "<?php echo round($Total_p_result_d, 2); ?>";
    var t5 = "<?php echo round($Total_p_result_e, 2); ?>";
    var options = {
      title: {
        text: "In Progress Job"
      },
      subtitles: [{
        text: "<?php echo $start_date; ?> - <?php echo $end_date; ?>"
      }],
      animationEnabled: true,
      legend: {
        horizontalAlign: "center",
        verticalAlign: "bottom",
        fontSize: 14,
      },
      data: [{
        type: "pie",
        startAngle: 45,
        showInLegend: "true",
        legendText: "{label}",
        indexLabel: "{label} ({y})%",
        yValueFormatString: "#,##0.#" % "",
        dataPoints: [{
            y: t1,
            label: "เปิดงานซ่อม รอศูนย์บริการมารับ"
          },
          {
            y: t2,
            label: "สินค้าจัดส่งเข้าศูนย์บริการ"
          },
          {
            y: t3,
            label: "อยู่ระหว่างดำเนินการซ่อมสินค้า"
          },
          {
            y: t4,
            label: "ซ่อมเสร็จเรียบร้อยแล้ว รอส่งกลับจุดรับบริการ"
          },
          {
            y: t5,
            label: "สินค้าถึงจุดรับบริการ รอลูกค้ามารับ"
          }
        ]
      }]
    };

    $("#chartContainer").CanvasJSChart(options);
  }
</script>