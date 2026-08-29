<link href="<?php echo base_url(); ?>assets/fonts/stylesheet.css" rel="stylesheet">

<style>
  .p-0 {
    padding: 0;
  }

  .pt-0 {
    padding-top: 0;
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

  .topic-txt-hm-sm {
    font: bold 2.6rem "db_helvethaica_x53_ext";
    color: #ffa500;
  }

  .topic-txt-hm-md {
    font: bold 2.2em "db_helvethaica_x53_ext";
    color: #004195;
  }

  .modal {
    position: fixed;
    top: 0;
    left: 0;
    display: none;
    width: 100%;
    height: 100%;
    overflow-x: hidden;
    overflow-y: auto;
    outline: 0;
  }

  .modal.modal-rating {
    padding: 0 !important;
  }

  .modal-dialog-centered {
    display: flex;
    align-items: center;
    min-height: calc(100% - 60px * 2);
  }

  .modal-fullscreen {
    width: 100vw;
    max-width: none;
    height: 100%;
    margin: 0;
  }

  .modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-clip: padding-box;
    outline: 0;
  }

  .modal-fullscreen .modal-content {
    height: 100%;
    border: 0;
    border-radius: 0;
  }

  .modal-fullscreen .modal-footer,
  .modal-fullscreen .modal-header {
    border-radius: 0
  }

  .modal-fullscreen .modal-body {
    overflow-y: auto;
    position: relative;
    flex: 1 1 auto;
  }

  .modal-fullscreen .modal-body::-webkit-scrollbar {
    width: 14px;
    height: 14px;
    background-color: #4c577d08;
    border-top: 0;
  }

  .modal-fullscreen .modal-body::-webkit-scrollbar-thumb {
    background-color: #8aa4af;
    border-radius: 0;
  }

  .logo-size {
    width: 100%;
    max-width: 220px;
    max-height: 56px;
    display: inline-block;
    vertical-align: top;
  }

  .close-btn-sm {
    margin: 0;
    padding: 10px;
    font: normal 1.5em "db_helvethaica_x53_ext";
    color: #FFFFFF;
    text-align: center;
    max-width: 170px;
    width: 100%;
    background: #004195;
    border: none;
  }

  .modal-header .close {
    float: right;
    font-size: 44px;
    font-weight: 500;
    line-height: 1;
    color: #000;
    text-shadow: 0 1px 0 #fff;
    filter: alpha(opacity=20);
    opacity: 0.6;
    margin-top: 6px;
    margin-right: 15px;
  }

  .modal-header .navbar-language {
    float: right !important;
    margin-top: 8px;
    margin-right: 20px;
  }

  .modal-header .navbar-language li {
    float: left;
    margin-left: 6px;
    margin-right: 6px;
  }

  .modal-header .navbar-language a {
    color: #333;
    width: 35px;
    display: block;
    text-align: center;
    padding: 0;
  }

  .modal-header .navbar-language a:hover,
  .modal-header .navbar-language a:focus,
  .modal-header .navbar-language a:active {
    background-color: transparent;
    color: #3c8dbc;
  }

  .modal-header .navbar-language img {
    width: 30px;
    height: 20px;
  }

  .modal-header .navbar-language .txt-lang {
    font-family: "db_helvethaica_x53_ext";
    font-size: 14px;
    font-weight: 600;
  }

  .modal.modal-rating .form-control {
    font-size: 18px;
  }

  .modal.modal-rating section#rating {
    padding: 0 0 20px;
  }

  .modal.modal-rating section#rating .star-rating-center {
    margin: 0;
  }

  .modal.modal-rating section#rating .main-btn-sm {
    margin: 10px 15px 20px 15px;
    padding: 10px;
    font-family: "db_helvethaica_x53_ext";
    font-size: 1.5em;
    font-weight: 600;
    color: #FFFFFF;
    text-align: center;
    max-width: 170px;
    width: 100%;
    background: #004195;
    border: none;
  }

  @media (max-width: 767px) {
    .modal-header .navbar-language {
      margin-right: 10px;
    }
  }
</style>
<style>
  .d-none {
    display: none !important;
  }

  .custom-checkbox {
    display: inline-block;
    width: 40px;
    height: 40px;
    border: 2px solid #333;
    border-radius: 5px;
    margin: 5px;
    text-align: center;
    line-height: 34px;
    cursor: pointer;
    font-size: 20px;
    font-weight: 600;
  }

  .custom-checkbox.selected {
    background-color: #ffa500;
    color: #FFFFFF;
    border-color: #ffa500;
  }
</style>
<div class="content-wrapper">
  <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
  <div class="content-table-form">
    <section class="content-header">
      <h1 class="text-uppercase"><i class="fa fa-link"></i> COMPLETE FEEDBACK</h1>
    </section>
    <section class="content">
      <div class="box">
        <div class="box-header p-0">
          <div class="row">
            <div class="col-xs-12">
              <form action="<?php echo base_url(); ?>TrackingcompleteListing" method="POST" id="searchList">
                <div class="row">
                  <div class="col-xs-12 col-md-3 form-col">
                    <div class="form-group">
                      <label class="d-block">Detail:</label>
                      <input type="text" id="searchText" name="searchText" value="<?php echo $searchText; ?>" class="form-control" placeholder="Search" autocomplete="off">
                    </div>
                  </div>
                  <div class="col-xs-12 col-md-3 form-col">
                    <div class="form-group">
                      <label class="d-block">Date:</label>
                      <input type="text" id="sdate" name="sdate" value="<?php echo $sdate; ?>" class="form-control" placeholder="Date" autocomplete="off">
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
        <div style="width: 100%; display: block; border-top: 1px solid #eff2f5; border-left: 1px solid #eff2f5; border-right: 1px solid #eff2f5; border-bottom: 0; border-radius: initial;" class="box-body no-padding">
          <table id="examples" class="table table-striped" cellspacing="0" width="100%">
            <thead>
              <tr>
                <th>Id</th>
                <th>TrackID</th>
                <th>OrderID</th>
                <th>Fullname</th>
                <th>Tel</th>
                <th>Email</th>
                <th>RequestDate</th>
                <th>Action Status</th>
                <th>Status Update</th>
                <th style="min-width: 60px; max-width: 60px; width: 60px;" class="text-center">Actions</th>
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

                foreach ($OrdersRecords as $record) {
                  $i++;
                  $dd = $record->requestDate;
                  $AA = substr($dd, 0, 4);
                  $BB = substr($dd, 5, 2);
                  $CC = substr($dd, 8, 2);
                  $DD = $AA;
                  $XX = $CC . "/" . $BB . "/" . $DD;
                  $trackID = $record->orderID;
                  $customerTel = $record->customerTel;
                  $ststus_update = $this->request_order_model->chack_status_update($trackID, $customerTel);
              ?>
                  <tr>
                    <td><?php echo $record->request_id; ?></td>
                    <td><?php echo $record->trackID; ?></td>
                    <td><?php echo $record->orderIDShow; ?></td>
                    <td><?php echo $record->customerFullname; ?></td>
                    <td><?php echo $record->customerTel; ?></td>
                    <td><?php echo $record->customerEmail; ?></td>
                    <td><?php echo $XX; ?></td>
                    <td><?php echo $record->status_name; ?></td>
                    <td><?php echo $ststus_update; ?></td>
                    <td class="text-center"><a class="btn btn-sm btn-info" href="javascript:void(0);" onclick="openModal('<?php echo $record->request_id; ?>', '<?php echo $record->trackID; ?>', '<?php echo $record->branchID; ?>');">ประเมิน</a></td>
                  </tr>
              <?php
                }
              }
              ?>
            </tbody>
          </table>
        </div>
        <div style="padding: 0; border-top: 0;" class="box-footer clearfix">
          <?php echo $this->pagination->create_links(); ?>
        </div>
      </div>
    </section>
  </div>
</div>

<div id="modal_rating" class="modal fade modal-rating" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="staticBackdropLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen" role="document">
    <div class="modal-content">
      <div class="modal-header p-0">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <img class="logo-size" src="<?php echo base_url(); ?>assets/images/main-logo.png">
        <ul class="nav navbar-nav navbar-right navbar-language">
          <li>
            <a href="javascript:void(0);" onclick="activeLanguageThai();">
              <div class="control-lang">
                <img src="<?php echo base_url(); ?>assets/images/thai.png" class="img-lang" alt="">
                <div class="txt-lang">ไทย</div>
              </div>
            </a>
          </li>
          <li>
            <a href="javascript:void(0);" onclick="activeLanguageEnglish();">
              <div class="control-lang">
                <img src="<?php echo base_url(); ?>assets/images/eng.png" class="img-lang" alt="">
                <div class="txt-lang">English</div>
              </div>
            </a>
          </li>
        </ul>
      </div>
      <div class="modal-body p-0">
        <section id="rating">
          <div class="banner-control">
            <img class="rs-bg-size" src="<?php echo base_url(); ?>uploads/web/trackstatus_laptop.png">
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating mb-0">
                <div class="topic-txt-hm language-thai" style="color: #ff6600; margin-bottom: 20px;">กรุณาประเมินความพึงพอใจในบริการของเราเพื่อช่วยให้เราพัฒนาได้ดียิ่งขึ้น</div>
                <div class="topic-txt-hm language-english" style="color: #ff6600; margin-bottom: 20px;">To help us improve our service, please rate your satisfaction.</div>
                <div class="topic-txt-hm-sm language-thai" style="margin-bottom: 20px;">1. ไม่พอใจมาก / 2. ไม่พอใจ / 3. ปานกลาง / 4. พอใจ / 5. พอใจมาก</div>
                <div class="topic-txt-hm-sm language-english" style="margin-bottom: 20px;">1. Very dissatisfied / 2. Dissatisfied / 3. Neutral / 4. Satisfied / 5. Very satisfied</div>
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center star-rating-one mb-0">
                <div class="topic-txt-hm-md language-thai" style="margin-bottom: 20px;">1. ความพึงพอใจในการให้บริการของเจ้าหน้าที่ ณ จุดรับซ่อม</div>
                <div class="topic-txt-hm-md language-english" style="margin-bottom: 20px;">1. How would you rate the helpfulness of our drop point service?</div>
                <span class="fa fa-star size" data-rating-one="1"></span>
                <span class="fa fa-star size" data-rating-one="2"></span>
                <span class="fa fa-star size" data-rating-one="3"></span>
                <span class="fa fa-star size" data-rating-one="4"></span>
                <span class="fa fa-star size" data-rating-one="5"></span>
                <input type="hidden" id="rating_1" name="rating_1" value="0">
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center star-rating-two mb-0">
                <div class="topic-txt-hm-md language-thai" style="margin-bottom: 20px;">2. ความพึงพอใจในการให้บริการของศูนย์บริการ</div>
                <div class="topic-txt-hm-md language-english" style="margin-bottom: 20px;">2. How would you rate our service center?</div>
                <span class="fa fa-star size" data-rating-two="1"></span>
                <span class="fa fa-star size" data-rating-two="2"></span>
                <span class="fa fa-star size" data-rating-two="3"></span>
                <span class="fa fa-star size" data-rating-two="4"></span>
                <span class="fa fa-star size" data-rating-two="5"></span>
                <input type="hidden" id="rating_2" name="rating_2" value="0">
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center star-rating-three mb-0">
                <div class="topic-txt-hm-md language-thai" style="margin-bottom: 20px;">3. ความพึงพอใจในคุณภาพงานซ่อม</div>
                <div class="topic-txt-hm-md language-english" style="margin-bottom: 20px;">3. Did our repair quality meet your expectation?</div>
                <span class="fa fa-star size" data-rating-three="1"></span>
                <span class="fa fa-star size" data-rating-three="2"></span>
                <span class="fa fa-star size" data-rating-three="3"></span>
                <span class="fa fa-star size" data-rating-three="4"></span>
                <span class="fa fa-star size" data-rating-three="5"></span>
                <input type="hidden" id="rating_3" name="rating_3" value="0">
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center star-rating-four mb-0">
                <div class="topic-txt-hm-md language-thai" style="margin-bottom: 20px;">4. ระยะเวลาที่ใช้ในการซ่อม</div>
                <div class="topic-txt-hm-md language-english" style="margin-bottom: 20px;">4. Did our repair lead time meet your expectation?</div>
                <span class="fa fa-star size" data-rating-four="1"></span>
                <span class="fa fa-star size" data-rating-four="2"></span>
                <span class="fa fa-star size" data-rating-four="3"></span>
                <span class="fa fa-star size" data-rating-four="4"></span>
                <span class="fa fa-star size" data-rating-four="5"></span>
                <input type="hidden" id="rating_4" name="rating_4" value="0">
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center mb-0">
                <div class="topic-txt-hm-md language-thai">5. ลำดับความสำคัญที่ลูกค้าพิจารณา<br>(1 คือให้ความสำคัญมากสุด)</div>
                <div class="topic-txt-hm-md language-english">5. Please sequence the most important factor for you?<br>(1 being the highest priority)</div>
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center star-rating-five mb-0">
                <div class="topic-txt-hm-md language-thai" style="margin-bottom: 10px;">ระยะเวลาซ่อม</div>
                <div class="topic-txt-hm-md language-english" style="margin-bottom: 10px;">Repair lead time</div>
                <div class="ratings" data-aspect="repair">
                  <div class="custom-checkbox" data-score="1">1</div>
                  <div class="custom-checkbox" data-score="2">2</div>
                  <div class="custom-checkbox" data-score="3">3</div>
                  <div class="custom-checkbox" data-score="4">4</div>
                </div>
                <span class="fa fa-star size d-none" data-rating-five="1"></span>
                <span class="fa fa-star size d-none" data-rating-five="2"></span>
                <span class="fa fa-star size d-none" data-rating-five="3"></span>
                <span class="fa fa-star size d-none" data-rating-five="4"></span>
                <span class="fa fa-star size d-none" data-rating-five="5"></span>
                <input type="hidden" id="rating_5" name="rating_5" value="0">
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center star-rating-six mb-0">
                <div class="topic-txt-hm-md language-thai" style="margin-bottom: 10px;">ค่าบริการซ่อม</div>
                <div class="topic-txt-hm-md language-english" style="margin-bottom: 10px;">Cost of the repair service</div>
                <div class="ratings" data-aspect="repairService">
                  <div class="custom-checkbox" data-score="1">1</div>
                  <div class="custom-checkbox" data-score="2">2</div>
                  <div class="custom-checkbox" data-score="3">3</div>
                  <div class="custom-checkbox" data-score="4">4</div>
                </div>
                <span class="fa fa-star size d-none" data-rating-six="1"></span>
                <span class="fa fa-star size d-none" data-rating-six="2"></span>
                <span class="fa fa-star size d-none" data-rating-six="3"></span>
                <span class="fa fa-star size d-none" data-rating-six="4"></span>
                <span class="fa fa-star size d-none" data-rating-six="5"></span>
                <input type="hidden" id="rating_6" name="rating_6" value="0">
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center star-rating-seven mb-0">
                <div class="topic-txt-hm-md language-thai" style="margin-bottom: 10px;">คุณภาพงานซ่อม</div>
                <div class="topic-txt-hm-md language-english" style="margin-bottom: 10px;">Quality of repair service</div>
                <div class="ratings" data-aspect="repairWork">
                  <div class="custom-checkbox" data-score="1">1</div>
                  <div class="custom-checkbox" data-score="2">2</div>
                  <div class="custom-checkbox" data-score="3">3</div>
                  <div class="custom-checkbox" data-score="4">4</div>
                </div>
                <span class="fa fa-star size d-none" data-rating-seven="1"></span>
                <span class="fa fa-star size d-none" data-rating-seven="2"></span>
                <span class="fa fa-star size d-none" data-rating-seven="3"></span>
                <span class="fa fa-star size d-none" data-rating-seven="4"></span>
                <span class="fa fa-star size d-none" data-rating-seven="5"></span>
                <input type="hidden" id="rating_7" name="rating_7" value="0">
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center star-rating-eight mb-0">
                <div class="topic-txt-hm-md language-thai" style="margin-bottom: 10px;">ความพึงพอใจในการบริการ</div>
                <div class="topic-txt-hm-md language-english" style="margin-bottom: 10px;">Quality of customer service</div>
                <div class="ratings" data-aspect="serveInService">
                  <div class="custom-checkbox" data-score="1">1</div>
                  <div class="custom-checkbox" data-score="2">2</div>
                  <div class="custom-checkbox" data-score="3">3</div>
                  <div class="custom-checkbox" data-score="4">4</div>
                </div>
                <span class="fa fa-star size d-none" data-rating-eight="1"></span>
                <span class="fa fa-star size d-none" data-rating-eight="2"></span>
                <span class="fa fa-star size d-none" data-rating-eight="3"></span>
                <span class="fa fa-star size d-none" data-rating-eight="4"></span>
                <span class="fa fa-star size d-none" data-rating-eight="5"></span>
                <input type="hidden" id="rating_8" name="rating_8" value="0">
              </div>
            </div>
          </div>
          <div class="container">
            <div class="row">
              <div class="star-rating-center mb-0">
                <div class="topic-txt-hm-md language-thai" style="margin-bottom: 20px;">6. ข้อเสนอแนะเพิ่มเติม</div>
                <div class="topic-txt-hm-md language-english" style="margin-bottom: 20px;">6. Any other feedback?</div>
                <div class="col-xs-12 col-md-10 col-md-offset-1">
                  <input type="hidden" id="request_id" name="request_id" value="" style="display: none;" autocomplete="off" readonly>
                  <input type="hidden" id="rating_track_id" name="rating_track_id" value="" style="display: none;" autocomplete="off" readonly>
                  <input type="hidden" id="rating_branch_id" name="rating_branch_id" value="" style="display: none;" autocomplete="off" readonly>
                  <textarea id="rating_comment" name="rating_comment" class="form-control" rows="6"></textarea>
                </div>
              </div>
            </div>
          </div>
          <div style="margin-top: 30px; margin-bottom: 20px;" class="container">
            <div class="row">
              <div class="col-md-12" style="text-align: center;">
                <button type="button" class="main-btn-sm" onclick="handleSubmitRating();">SUBMIT</button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

<div id="modal_rating_successful" class="modal modal-rating" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="staticBackdropLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header p-0">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <img class="logo-size" src="<?php echo base_url(); ?>assets/images/main-logo.png">
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-xs-12 text-center">
            <div class="topic-txt-hm language-thai" style="font-size: 2em; color: #004195; margin-top: 20px; margin-bottom: 20px;">ขอบคุณสำหรับคะแนนความพึงพอใจและการแสดงความคิดเห็น</div>
            <div class="topic-txt-hm language-english" style="font-size: 2em; color: #004195; margin-top: 20px; margin-bottom: 20px;">Thank you for your satisfaction rating and feedback.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="modal_rating_unsuccessful" class="modal modal-rating" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="staticBackdropLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header p-0">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <img class="logo-size" src="<?php echo base_url(); ?>assets/images/main-logo.png">
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-xs-12 text-center">
            <div class="topic-txt-hm language-thai" style="font-size: 2em; color: #ff6600; margin-top: 20px; margin-bottom: 20px;">ขออภัย! พบข้อผิดพลาดในการประมวลผลไม่สำเร็จ</div>
            <div class="topic-txt-hm language-english" style="font-size: 2em; color: #ff6600; margin-top: 20px; margin-bottom: 20px;">Sorry an error occurred, processing was unsuccessful.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  $(document).ready(function() {
    const selectedScores = {};
    const aspectToInputId = {
      repair: '#rating_5',
      repairService: '#rating_6',
      repairWork: '#rating_7',
      serveInService: '#rating_8'
    };

    $('.custom-checkbox').click(function() {
      const score = $(this).data('score');
      const aspect = $(this).closest('.ratings').data('aspect');
      const inputId = aspectToInputId[aspect];

      if ($(this).hasClass('selected')) {
        delete selectedScores[aspect];
        $(this).removeClass('selected');
        $(inputId).val('');
      } else {
        const previousSelection = selectedScores[aspect];
        if (previousSelection) {
          $(`[data-aspect="${aspect}"] .custom-checkbox[data-score="${previousSelection}"]`).removeClass('selected');
        }

        selectedScores[aspect] = score;
        $(this).addClass('selected');
        $(inputId).val(score);
      }

      const allSelectedScores = Object.values(selectedScores);
      if (allSelectedScores.length !== new Set(allSelectedScores).size) {
        if (activeLanguage == 'en') {
          alert("Please select unique scores.");
        } else {
          alert("โปรดเลือกคะแนนที่ไม่ซ้ำ");
        }

        delete selectedScores[aspect];
        $(this).removeClass('selected');
        $(inputId).val('');
      }
    });
  });
</script>

<script>
  let base_url = "<?php echo base_url(); ?>";
  let starRatingOne = $('.star-rating-one .fa');
  let starRatingTwo = $('.star-rating-two .fa');
  let starRatingThree = $('.star-rating-three .fa');
  let starRatingFour = $('.star-rating-four .fa');
  let starRatingFive = $('.star-rating-five .fa');
  let starRatingSix = $('.star-rating-six .fa');
  let starRatingSeven = $('.star-rating-seven .fa');
  let starRatingEight = $('.star-rating-eight .fa');
  let dateBefore = null;
  let activeLanguage = 'th';

  let setStarRatingOne = function() {
    return starRatingOne.each(function() {
      if (parseInt(starRatingOne.siblings('input#rating_1').val()) >= parseInt($(this).data('rating-one'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  let setStarRatingTwo = function() {
    return starRatingTwo.each(function() {
      if (parseInt(starRatingTwo.siblings('input#rating_2').val()) >= parseInt($(this).data('rating-two'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  let setStarRatingThree = function() {
    return starRatingThree.each(function() {
      if (parseInt(starRatingThree.siblings('input#rating_3').val()) >= parseInt($(this).data('rating-three'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  let setStarRatingFour = function() {
    return starRatingFour.each(function() {
      if (parseInt(starRatingFour.siblings('input#rating_4').val()) >= parseInt($(this).data('rating-four'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  let setStarRatingFive = function() {
    return starRatingFive.each(function() {
      if (parseInt(starRatingFive.siblings('input#rating_5').val()) >= parseInt($(this).data('rating-five'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  let setStarRatingSix = function() {
    return starRatingSix.each(function() {
      if (parseInt(starRatingSix.siblings('input#rating_6').val()) >= parseInt($(this).data('rating-six'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  let setStarRatingSeven = function() {
    return starRatingSeven.each(function() {
      if (parseInt(starRatingSeven.siblings('input#rating_7').val()) >= parseInt($(this).data('rating-seven'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  let setStarRatingEight = function() {
    return starRatingEight.each(function() {
      if (parseInt(starRatingEight.siblings('input#rating_8').val()) >= parseInt($(this).data('rating-eight'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  starRatingOne.on('click', function() {
    starRatingOne.siblings('input#rating_1').val($(this).data('rating-one'));

    return setStarRatingOne();
  });

  starRatingTwo.on('click', function() {
    starRatingTwo.siblings('input#rating_2').val($(this).data('rating-two'));

    return setStarRatingTwo();
  });

  starRatingThree.on('click', function() {
    starRatingThree.siblings('input#rating_3').val($(this).data('rating-three'));

    return setStarRatingThree();
  });

  starRatingFour.on('click', function() {
    starRatingFour.siblings('input#rating_4').val($(this).data('rating-four'));

    return setStarRatingFour();
  });

  starRatingFive.on('click', function() {
    starRatingFive.siblings('input#rating_5').val($(this).data('rating-five'));

    return setStarRatingFive();
  });

  starRatingSix.on('click', function() {
    starRatingSix.siblings('input#rating_6').val($(this).data('rating-six'));

    return setStarRatingSix();
  });

  starRatingSeven.on('click', function() {
    starRatingSeven.siblings('input#rating_7').val($(this).data('rating-seven'));

    return setStarRatingSeven();
  });

  starRatingEight.on('click', function() {
    starRatingEight.siblings('input#rating_8').val($(this).data('rating-eight'));

    return setStarRatingEight();
  });

  function openModal(requestId, trackId, branchId) {
    $('#request_id').val(requestId);
    $('#rating_track_id').val(trackId);
    $('#rating_branch_id').val(branchId);

    $('#modal_rating').modal('show');
  }

  function handleSubmitRating() {
    const requestId = parseInt($('#request_id').val());
    const ratingTrackId = $('#rating_track_id').val();
    const ratingBranchId = $('#rating_branch_id').val();
    const ratingOne = parseInt($('#rating_1').val());
    const ratingTwo = parseInt($('#rating_2').val());
    const ratingThree = parseInt($('#rating_3').val());
    const ratingFour = parseInt($('#rating_4').val());
    const ratingFive = parseInt($('#rating_5').val());
    const ratingSix = parseInt($('#rating_6').val());
    const ratingSeven = parseInt($('#rating_7').val());
    const ratingEight = parseInt($('#rating_8').val());
    const ratingComment = $('#rating_comment').val();

    if (requestId < 1) {
      if (activeLanguage == 'en') {
        alert("Sorry an error occurred, processing was unsuccessful.");
      } else {
        alert("ขออภัย! พบข้อผิดพลาดในการประมวลผลไม่สำเร็จ");
      }

      return false;
    }

    if (ratingOne < 1 || ratingOne > 6) {
      if (activeLanguage == 'en') {
        alert("1. How would you rate the helpfulness of our drop point service? (1 - 5)");
      } else {
        alert("1. ความพึงพอใจในการให้บริการของเจ้าหน้าที่ ณ จุดรับซ่อม (1 - 5)");
      }

      return false;
    }

    if (ratingTwo < 1 || ratingTwo > 6) {
      if (activeLanguage == 'en') {
        alert("2. How would you rate our service center? (1 - 5)");
      } else {
        alert("2. ความพึงพอใจในการให้บริการของศูนย์บริการ (1 - 5)");
      }

      return false;
    }

    if (ratingThree < 1 || ratingThree > 6) {
      if (activeLanguage == 'en') {
        alert("3. Did our repair quality meet your expectation? (1 - 5)");
      } else {
        alert("3. ความพึงพอใจในคุณภาพงานซ่อม (1 - 5)");
      }

      return false;
    }

    if (ratingFour < 1 || ratingFour > 6) {
      if (activeLanguage == 'en') {
        alert("4. Did our repair lead time meet your expectation? (1 - 4)");
      } else {
        alert("4. ระยะเวลาที่ใช้ในการซ่อม (1 - 4)");
      }

      return false;
    }

    if (ratingFive < 1 || ratingFive > 6) {
      if (activeLanguage == 'en') {
        alert("5. Please sequence the most important factor for you?\nRepair lead time (1 - 4)");
      } else {
        alert("5. ลำดับความสำคัญที่ลูกค้าพิจารณา\nระยะเวลาซ่อม (1 - 4)");
      }

      return false;
    }

    if (ratingSix < 1 || ratingSix > 6) {
      if (activeLanguage == 'en') {
        alert("5. Please sequence the most important factor for you?\nCost of the repair service (1 - 4)");
      } else {
        alert("5. ลำดับความสำคัญที่ลูกค้าพิจารณา\nค่าบริการซ่อม (1 - 4)");
      }

      return false;
    }

    if (ratingSeven < 1 || ratingSeven > 6) {
      if (activeLanguage == 'en') {
        alert("5. Please sequence the most important factor for you?\nQuality of repair service (1 - 4)");
      } else {
        alert("5. ลำดับความสำคัญที่ลูกค้าพิจารณา\nคุณภาพงานซ่อม (1 - 4)");
      }

      return false;
    }

    if (ratingEight < 1 || ratingEight > 6) {
      if (activeLanguage == 'en') {
        alert("5. Please sequence the most important factor for you?\nQuality of customer service (1 - 4)");
      } else {
        alert("5. ลำดับความสำคัญที่ลูกค้าพิจารณา\nความพึงพอใจในการบริการ (1 - 4)");
      }

      return false;
    }

    let formData = new FormData();

    formData.append('requestId', requestId);
    formData.append('ratingTrackId', ratingTrackId);
    formData.append('ratingBranchId', ratingBranchId);
    formData.append('ratingOne', ratingOne);
    formData.append('ratingTwo', ratingTwo);
    formData.append('ratingThree', ratingThree);
    formData.append('ratingFour', ratingFour);
    formData.append('ratingFive', ratingFive);
    formData.append('ratingSix', ratingSix);
    formData.append('ratingSeven', ratingSeven);
    formData.append('ratingEight', ratingEight);
    formData.append('ratingComment', ratingComment);

    $.ajax({
      url: base_url + 'rating/addRating',
      type: 'POST',
      dataType: "json",
      data: formData,
      async: false,
      cache: false,
      contentType: false,
      processData: false,
      headers: {
        "cache-control": "no-cache"
      },
      success: function(response) {
        $('#modal_rating').modal('hide');
        $('#modal_rating_successful').modal('hide');
        $('#modal_rating_unsuccessful').modal('hide');

        if ((typeof response.status !== null && typeof response.status !== undefined && response.status === true)) {
          $('#modal_rating_successful').modal('show');
        } else {
          $('#modal_rating_unsuccessful').modal('show');
        }
      },
      error: function(data, textStatus, errorThrown) {
        $('#modal_rating').modal('hide');
        $('#modal_rating_successful').modal('hide');
        $('#modal_rating_unsuccessful').modal('show');
      }
    }).done(function(data) {

    });
  }

  function activeLanguageThai() {
    activeLanguage = 'th';

    $('.language-thai').css('display', 'block');
    $('.language-english').css('display', 'none');
  }

  function activeLanguageEnglish() {
    activeLanguage = 'en';

    $('.language-thai').css('display', 'none');
    $('.language-english').css('display', 'block');
  }

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

    $("#sdate").datepicker({
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

    $('#modal_rating').on('show.bs.modal', function(e) {
      activeLanguageThai();
    });

    $('#modal_rating').on('hidden.bs.modal', function(e) {
      $('#request_id').val('');
      $('#rating_track_id').val('');
      $('#rating_branch_id').val('');
      $('#rating_1').val(0);
      $('#rating_2').val(0);
      $('#rating_3').val(0);
      $('#rating_4').val(0);
      $('#rating_5').val(0);
      $('#rating_6').val(0);
      $('#rating_7').val(0);
      $('#rating_8').val(0);
      $('#rating_comment').val('');

      setStarRatingOne();
      setStarRatingTwo();
      setStarRatingThree();
      setStarRatingFour();
      setStarRatingFive();
      setStarRatingSix();
      setStarRatingSeven();
      setStarRatingEight();
    });

    $('#modal_rating_successful').on('hidden.bs.modal', function(e) {
      window.location.reload();
    });

    $('#modal_rating_unsuccessful').on('hidden.bs.modal', function(e) {
      window.location.reload();
    });

    $('.modal').on('shown.bs.modal', function(e) {
      $('body').css('overflow-y', 'hidden');
    });

    $('.modal').on('hide.bs.modal', function(e) {
      $('body').css('overflow-y', '');

      const scrollBody = document.querySelector('.modal-body');

      if (scrollBody) {
        scrollBody.scrollTop = 0;
      }

    });

    setStarRatingOne();
    setStarRatingTwo();
    setStarRatingThree();
    setStarRatingFour();
    setStarRatingFive();
    setStarRatingSix();
    setStarRatingSeven();
    setStarRatingEight();
  });
</script>