<link href="<?php echo base_url(); ?>assets/fonts/stylesheet.css" rel="stylesheet">

<style>
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
    border-bottom: 0 !important;
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
    min-height: 47px;
  }

  table.dataTable tbody th,
  table.dataTable tbody td {
    vertical-align: top !important;
    white-space: nowrap;
  }

  div.dataTables_wrapper {
    border-bottom: 1px solid #ededed !important;
    margin: 0 auto;
  }

  table.dataTable tr:first-child td {
    border-top: 0 !important;
  }

  table.dataTable tr:last-child td {
    border-bottom: 0 !important;
  }

  @media (max-width: 1199px) {
    table.dataTable tbody td {
      white-space: normal;
    }
  }
</style>
<div class="content-wrapper">
  <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
  <div class="content-table-form container-content">
    <section class="content-header">
      <div class="row">
        <div class="col-xs-8">
          <h1 class="text-uppercase"><i class="fa fa-cart-arrow-down"></i> Rating Report</h1>
        </div>
        <div class="col-xs-4">
          <div class="pull-right">
            <a class="btn btn-primary" href="<?php echo base_url(); ?>user/excel_ratings/<?php echo $BranchID . '/' . str_replace('/', '-', $start_date) . '/' . str_replace('/', '-', $end_date); ?>" target="_blank">Export</a>
          </div>
        </div>
      </div>
    </section>
    <section class="content">
      <div class="box">
        <div class="box-header pt-0 px-0">
          <div class="row">
            <div class="col-xs-12">
              <form action="<?php echo base_url(); ?>user/report" method="POST" id="searchList">
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
        <?php
        if ($GroupID < 3) {
        ?>
          <div class="row">
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>1. ความพึงพอใจในการให้บริการของเจ้าหน้าที่ ณ จุดรับซ่อม</h4>
                  <h5>Total <?php echo number_format($ratings['group'][1]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][1]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][1]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][1]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][1]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][5]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_e = 'style="width: ' . $ratings['group'][1]['average'][5] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_e; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>2. ความพึงพอใจในการให้บริการของศูนย์บริการ</h4>
                  <h5>Total <?php echo number_format($ratings['group'][2]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][2]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][2]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][2]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][2]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][2]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][2]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][2]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][2]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][2]['average'][5]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_e = 'style="width: ' . $ratings['group'][2]['average'][5] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_e; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>3. ความพึงพอใจในคุณภาพงานซ่อม</h4>
                  <h5>Total <?php echo number_format($ratings['group'][3]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][3]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][3]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][3]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][3]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][3]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][3]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][3]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][3]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][3]['average'][5]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_e = 'style="width: ' . $ratings['group'][3]['average'][5] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_e; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>4. ระยะเวลาที่ใช้ในการซ่อม</h4>
                  <h5>Total <?php echo number_format($ratings['group'][4]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][4]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][4]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][4]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][4]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][4]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][4]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][4]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][4]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][4]['average'][5]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_e = 'style="width: ' . $ratings['group'][4]['average'][5] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_e; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>5. ลำดับความสำคัญที่ลูกค้าพิจารณา</h4>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>5.1 ระยะเวลาซ่อม</h4>
                  <h5>Total <?php echo number_format($ratings['group'][5]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][5]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][5]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][5]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][5]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][5]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][5]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][5]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][5]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>5.2 ค่าบริการซ่อม</h4>
                  <h5>Total <?php echo number_format($ratings['group'][6]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][6]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][6]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][6]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][6]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][6]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][6]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][6]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][6]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>5.3 คุณภาพงานซ่อม</h4>
                  <h5>Total <?php echo number_format($ratings['group'][7]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][7]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][7]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][7]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][7]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][7]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][7]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][7]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][7]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>5.4 ความพึงพอใจในการบริการ</h4>
                  <h5>Total <?php echo number_format($ratings['group'][8]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][8]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][8]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][8]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][8]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][8]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][8]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][8]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][8]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        <?php } else if ($GroupID == 3) { ?>

          <div class="row">
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>1. ความพึงพอใจในการให้บริการของเจ้าหน้าที่ ณ จุดรับซ่อม</h4>
                  <h5>Total <?php echo number_format($ratings['group'][1]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][1]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][1]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][1]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][1]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][5]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_e = 'style="width: ' . $ratings['group'][1]['average'][5] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_e; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        <?php } else { ?>

          <div class="row">
            <div class="col-lg-6 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_content">
                  <h4>1. ความพึงพอใจในการให้บริการของเจ้าหน้าที่ ณ จุดรับซ่อม</h4>
                  <h5>Total <?php echo number_format($ratings['group'][1]['total'], 0); ?></h5>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][1]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_a = 'style="width: ' . $ratings['group'][1]['average'][1] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_a; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][2]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_b = 'style="width: ' . $ratings['group'][1]['average'][2] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_b; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][3]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_c = 'style="width: ' . $ratings['group'][1]['average'][3] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_c; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][4]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_d = 'style="width: ' . $ratings['group'][1]['average'][4] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_d; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                  <div class="widget_summary">
                    <div class="w_left w_25">
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span class="fa size fa-star"></span>
                      <span><?php echo $ratings['group'][1]['average'][5]; ?>%</span>
                    </div>
                    <div class="w_center w_55">
                      <div class="progress">
                        <?php $style_e = 'style="width: ' . $ratings['group'][1]['average'][5] . '%;"'; ?>
                        <div class="progress-bar bg-green" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" <?php echo $style_e; ?>>
                        </div>
                      </div>
                    </div>
                    <div class="clearfix"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php } ?>

        <h4>6. ข้อเสนอแนะเพิ่มเติม</h4>
        <div style="width: 100%; display: block; border-top: 1px solid #eff2f5; border-left: 1px solid #eff2f5; border-right: 1px solid #eff2f5; border-bottom: 0; border-radius: initial;" class="box-body no-padding">
          <table id="examples" class="table table-striped" cellspacing="0" width="100%">
            <thead>
              <tr>
                <th class="text-center">No</th>
                <th class="min-w-260px">Note</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($ratingComments)) { ?>
                <?php $row = 1; ?>
                <?php foreach ($ratingComments as $commentKey => $commentValue) {  ?>
                  <tr>
                    <td class="text-center"><?php echo $row; ?></td>
                    <td class="white-space min-w-260px max-w-800px"><?php echo $commentValue['comment']; ?></td>
                  </tr>
                  <?php $row++; ?>
                <?php } ?>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</div>

<script type="text/javascript">
  function search_status() {
    $("#searchList").submit();
  }
  $(function() {
    var dateBefore = null;
    $("#start_date").datepicker({
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
</script>
<script>
  $(document).ready(function() {
    var table = null;
    const scrollY = '60vh';
    const isMobile = window.matchMedia("(min-width: 1200px)").matches;
    const fixedColumns = isMobile ? {
      leftColumns: 1,
      rightColumns: 0
    } : undefined;

    table = $('#examples').DataTable({
      autoWidth: false,
      searching: false,
      paging: false,
      ordering: false,
      info: false,
      pageLength: -1,
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