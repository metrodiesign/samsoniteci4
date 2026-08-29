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
<div class="content-wrapper">
  <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
  <div class="content-table-form">
    <section class="content-header">
      <h1 class="text-uppercase"><i class="fa fa-link"></i> COMPLETED JOB</h1>
    </section>
    <section class="content">
      <div class="box">
        <div class="box-header p-0">
          <div class="row">
            <div class="col-xs-12">
              <form action="<?php echo base_url(); ?>TrackingCompletedListing" method="POST" id="searchList">
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
                <th>Track Id</th>
                <th>Order Id</th>
                <th>Full Name</th>
                <th>Tel</th>
                <th>Email</th>
                <th>Request Date</th>
                <th>Completed Date</th>
                <th>Action Status</th>
                <th>Status Update</th>
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

                  $requestDate = $record->requestDate;
                  $requestYear = substr($requestDate, 0, 4);
                  $requestMonth = substr($requestDate, 5, 2);
                  $requestDay = substr($requestDate, 8, 2);
                  $requestDateFormat = $requestDay . "/" . $requestMonth . "/" . $requestYear;

                  $completeDate = $record->date_complete;
                  $completeYear = substr($completeDate, 0, 4);
                  $completeMonth = substr($completeDate, 5, 2);
                  $completeDay = substr($completeDate, 8, 2);
                  $completeDateFormat = $completeDay . "/" . $completeMonth . "/" . $completeYear;

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
                    <td><?php echo $requestDateFormat; ?></td>
                    <td><?php echo $completeDateFormat; ?></td>
                    <td><?php echo $record->status_name; ?></td>
                    <td><?php echo $ststus_update; ?></td>
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

<script>
  let base_url = "<?php echo base_url(); ?>";
  let dateBefore = null;

  $(document).ready(function() {
    var table = null;
    const scrollY = '60vh';
    const isMobile = window.matchMedia("(min-width: 1200px)").matches;
    const fixedColumns = isMobile ? {
      leftColumns: 3,
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
  });
</script>