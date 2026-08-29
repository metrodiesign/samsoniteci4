<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
  <div class="content-form">
    <?php $this->load->helper("form"); ?>
    <form role="form" id="addOrder" action="<?php echo base_url() ?>addNewOrders" method="post" role="form" enctype="multipart/form-data">

      <section class="content-header">
        <div class="row">
          <div class="col-xs-8">
            <h1>
              <i class="fa fa-cart-arrow-down"></i> NEW REQUEST REPAIR

            </h1>
          </div>
          <!-- <div class="col-xs-4 text-right"> -->
          <!-- <div class="box-footer"> -->
          <!-- <input type="button" class="btn btn-primary" value="Submit" id="send_order_new" /> -->
          <!-- <input type="reset" class="btn btn-default" value="Reset" /> -->
          <!-- </div> -->
          <!-- </div> -->
        </div>
      </section>
      <section class="content">

        <div class="row">
          <!-- left column -->
          <div class="col-md-12">
            <!-- general form elements -->
            <div class="box box-primary">
              <div class="row">
                <div class="col-sm-6 col-lg-6">
                  <div class="box-header">
                    <h3 class="box-title">Enter Request order Details</h3>
                  </div><!-- /.box-header -->
                </div>
                <div class="col-sm-6 col-lg-6">
                  <div class="box-header" style="text-align: right;">
                    <h3 style="font-weight: bold; border-bottom: 1px solid #000;" class="box-title">Urgent/ซ่อมด่วน</h3>
                    <label class="custom-form" style="font-size: 18px; padding-left: 5px; width: auto;">
                      <input type="checkbox" name="detailAgent" id="detailAgent" value="1">
                      <span class="label-text">มี</span>
                    </label>
                  </div><!-- /.box-header -->
                </div>
              </div>
              <!-- form start -->

              <div class="box-body">
                <div class="row">
                  <div class="col-md-12">
                    <div class="form-group">
                      <span id="images_tump_name"></span>
                    </div>
                  </div>
                  <?php
                  if (!empty($BranchID) and $BranchID != 0) {

                  ?>
                    <div class="col-sm-12 col-lg-6">
                      <div class="form-group title-form">
                        <label for="requestDate">request Date/วันที่ส่งซ่อม <span class="remark">*</span></label>
                        <input type="text" class="form-control required" value="<?php echo $requestDate; ?>" id="requestDate" name="requestDate" readonly placeholder="MM/DD/YYYY">
                      </div>
                    </div>
                    <div class="col-sm-12 col-lg-6">
                      <div class="row">
                        <div class="col-sm-12 col-lg-6" style="display:none">
                          <div class="form-group">
                            <label for="branchshort">branch short/ตัวย่อสาขา <span class="remark">*</span></label>
                            <div id="department_branchshort"><input type="text" class="form-control" id="branchshort" name="branchshort" value="<?php echo $branchshort; ?>"></div>

                          </div>
                        </div>

                        <div class="col-sm-12 col-lg-6">
                          <div class="form-group">
                            <label for="bookshort">book Short/เล่มที่ <span class="remark">*</span></label>
                            <div id="department_tree"><input type="text" class="form-control" id="bookshort" name="bookshort" value=""></div>
                          </div>
                        </div>
                        <div class="col-sm-12 col-lg-6">
                          <div class="form-group">
                            <label for="numberID">number ID/เลขที <span class="remark">*</span></label>
                            <input type="text" class="form-control" id="numberID" value="" placeholder="000000" name="numberID" onKeyUp="if(event.keyCode !=37 &amp;&amp; event.keyCode != 39)  value=value.replace(/\D/g,'');">
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php } else { ?>
                    <div class="col-sm-12 col-lg-6">
                      <div class="form-group">
                        <label for="requestDate">request Date/วันที่ส่งซ่อม <span class="remark">*</span></label>
                        <input type="text" class="form-control required" value="<?php echo $requestDate; ?>" id="requestDate" name="requestDate" readonly placeholder="DD/MM/YYYY">
                      </div>
                    </div>
                    <div class="col-sm-12 col-lg-6">
                      <div class="form-group">
                        <label for="branch_type">Branch Type/ประเภทของสาขา <span class="remark">*</span></label>
                        <select class="form-control required" id="branch_type" name="branch_type" onchange="JavaScript:list_recommend_do_ajax(document.getElementById('branch_type').value)">
                          <option value="0">Select Branch Type</option>
                          <?php
                          if (!empty($branchtypes)) {
                            foreach ($branchtypes as $rl) {
                          ?>
                              <option value="<?php echo $rl->branch_type_id ?>" <?php if ($rl->branch_type_id == set_value('branch_type')) {
                                                                                  echo "selected=selected";
                                                                                } ?>>
                                <?php echo $rl->branch_type_details ?></option>
                          <?php
                            }
                          }
                          ?>
                        </select>
                      </div>
                    </div>
                    <div class="col-sm-12 col-lg-6">
                      <div class="row">
                        <div class="col-sm-12 col-lg-6">
                          <div class="form-group">
                            <label for="branch_id">Branch/สาขา <span class="remark">*</span></label>
                            <div id="department_one">
                              <select class="form-control required" id="branch_id" name="branch_id">
                                <option value="0">Select Branch</option>
                              </select>
                            </div>
                          </div>
                        </div>
                        <div class="col-sm-12 col-lg-6">
                          <div class="form-group">
                            <label for="branchshort">branch short/ตัวย่อสาขา <span class="remark">*</span></label>
                            <div id="department_branchshort"><input type="text" class="form-control" id="branchshort" name="branchshort" readonly></div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="col-sm-12 col-lg-6">
                      <div class="row">
                        <div class="col-sm-12 col-lg-6">
                          <div class="form-group">
                            <label for="bookshort">book Short/เล่มที่ <span class="remark">*</span></label>
                            <div id="department_tree"><input type="text" class="form-control" id="bookshort" name="bookshort"></div>
                          </div>
                        </div>

                        <div class="col-sm-12 col-lg-6">
                          <div class="form-group">
                            <label for="numberID">number ID/เลขที <span class="remark">*</span></label>
                            <input type="text" class="form-control" id="numberID" value="" placeholder="000000" name="numberID" onKeyUp="if(event.keyCode  !=37  &amp;&amp;  event.keyCode  !=  39)  value=value.replace(/\D/g,'');">
                          </div>
                        </div>
                      </div>

                    </div>
                  <?php
                  }
                  ?>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="customerFullname">customer Fullname/ชื่อลูกค้า <span class="remark">*</span></label>
                      <input type="text" class="form-control required" placeholder="ชื่อ-สกุล" value="<?php echo set_value('customerFullname'); ?>" id="customerFullname" name="customerFullname">
                    </div>

                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="email">customer Email/อีเมล์ลูกค้า </label>
                      <input type="text" class="form-control" id="email" placeholder="customer@gmail.com" value="<?php echo set_value('email'); ?>" name="email" maxlength="128">
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="customerTel">MOBILE TEL/เบอร์มือถือลูกค้า<span class="remark">*</span></label>
                      <input type="text" class="form-control required" id="customerTel" name="customerTel" maxlength="10" placeholder="xxxxxxxxxx" onKeyUp="if(event.keyCode  !=37  &amp;&amp;  event.keyCode  !=  39)  value=value.replace(/\D/g,'');">
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="customerTel2">MOBILE TEL 2/เบอร์โทรศัพท์ลูกค้า2</label>
                      <input type="text" class="form-control" id="customerTelTwo" maxlength="10" name="customerTelTwo" placeholder="xxxxxxxxxx" onKeyUp="if(event.keyCode  !=37  &amp;&amp;  event.keyCode  !=  39)  value=value.replace(/\D/g,'');">
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="role">Brand Id/ยี่ห้อ <span class="remark">*</span></label>
                      <select class="form-control required" id="detailBrandId" name="detailBrandId">
                        <option value="0">Select BrandId</option>
                        <?php
                        if (!empty($Brand)) {
                          foreach ($Brand as $bl) {
                        ?>
                            <option value="<?php echo $bl->brand_id ?>" <?php if ($bl->brand_id == set_value('detailBrandId')) {
                                                                          echo "selected=selected";
                                                                        } ?>><?php echo $bl->brand_details ?></option>
                        <?php
                          }
                        }
                        ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label>PURCHASED DATE/วันที่ซื้อ </label>
                      <input type="text" class="form-control" id="detailDatePurchase" name="detailDatePurchase" readonly>
                      <input type="hidden" class="form-control" value="<?php echo $times; ?>" id="times" name="times">
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="detailTypeId">Category/ประเภท <span class="remark">*</span></label>
                      <select class="form-control required" id="detailTypeId" name="detailTypeId">
                        <option value="0">Select Category</option>
                        <?php
                        if (!empty($Producttype)) {
                          foreach ($Producttype as $ptl) {
                        ?>

                            <option value="<?php echo $ptl->type_id ?>" <?php if ($ptl->type_id == set_value('detailTypeId')) {
                                                                          echo "selected=selected";
                                                                        } ?>><?php echo $ptl->type_details ?></option>
                        <?php
                          }
                        }
                        ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="detailEquipment">Equipment/อุปกรณ์ที่มาพร้อมกับสินค้า </label><BR>
                      <textarea rows="3" name="detailEquipment" id="detailEquipment" class="form-control"></textarea>
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="detailNote">Note/หมายเหตุ </label><BR>
                      <textarea rows="3" name="detailNote" id="detailNote" class="form-control"></textarea>
                    </div>
                  </div>
                </div><!-- /row -->
              </div><!-- /.box-body -->



            </div>
            <div class="box box-primary">
              <div class="box-body">
                <div class="row">
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label class="custom-control custom-radio" for="warantyType">waranty Type/ประเภทการรับประกัน <span class="remark">*</span></label><BR>
                      <label class="custom-form">
                        <input type="radio" name="warantyType" id="warantyType_1" value="0" checked>
                        <span class="label-text">ไม่มี</span>
                      </label>
                      <label class="custom-form">
                        <input type="radio" name="warantyType" id="warantyType_2" value="1">
                        <span class="label-text">มี</span>
                      </label>
                      <!-- <input class="custom-control-input" type="radio" name="warantyType"  name="warantyType" value="0"checked> &nbsp; ไม่มี &nbsp; -->
                      <!-- <input class="custom-control-input" type="radio" name="warantyType"  name="warantyType" value="1"> &nbsp; มี &nbsp; -->
                    </div>
                  </div>


                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="detailSKUName">SKU Name/ชื่อสินค้า <span class="remark">*</span></label>
                      <input type="text" class="form-control required" id="detailSKUName" name="detailSKUName" maxlength="20">
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6" style="display:none" id="detailNumberWaranty_id">
                    <div class="form-group">
                      <label for="detailNumberWaranty">Number Waranty/หมายเลขประกัน</label>
                      <input type="text" class="form-control" id="detailNumberWaranty" name="detailNumberWaranty">
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="detailCondition">Condition/อาการที่ส่งซ่อม <span class="remark">*</span></label><BR>

                      <?php
                      if (!empty($Condition)) {
                        $cx = 1;
                        foreach ($Condition as $cl) {
                      ?>
                          <label class="custom-form">
                            <input type="checkbox" id="condition[]" name="condition[]" value="<?php echo $cl->condition_id ?>">
                            <span class="label-text"><?php echo $cl->condition_details ?></span>
                          </label>


                        <?php
                          $cx++;
                        }
                        ?>
                        <input type="hidden" class="form-check-input" name="condition_count" value="<?php echo $cx; ?>">
                        <label class="custom-form">
                          <input type="checkbox" class="form-check-input" id="condition_etc" name="condition_etc" value="condition_etc">
                          <span class="label-text">อื่นๆ</span>
                        </label>

                        <input type="text" class="form-control" id="detailConditionOther" name="detailConditionOther">
                      <?php
                      }
                      ?>
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="detailEstimatePrice">Estimate Price/ประเมินราคาส่งซ่อม <span class="remark">*</span></label><BR>

                      <?php
                      if (!empty($Estimateprice)) {
                        $ex = 1;
                        foreach ($Estimateprice as $ep) {
                      ?>
                          <label class="custom-form">
                            <input type="checkbox" id="estimateprice[]" name="estimateprice[]" value="<?php echo $ep->estimateprice_id; ?>">
                            <span class="label-text"><?php echo $ep->estimateprice_details; ?></span>
                          </label>
                        <?php
                          $ex++;
                        }
                        ?>
                        <input type="hidden" class="form-check-input" name="estimateprice_count" value="<?php echo $ex; ?>">
                        <label class="custom-form">
                          <input type="checkbox" id="estimateprice_etc" name="estimateprice_etc" value="estimateprice_etc">
                          <span class="label-text">อื่นๆ</span>
                        </label>
                        <input type="text" class="form-control" id="detailEstimatePriceOther" name="detailEstimatePriceOther">
                      <?php
                      }
                      ?>
                    </div>
                  </div>
                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="role">Fixed/สภาพ,ตำหนิ <span class="remark">*</span></label><BR>

                      <?php
                      if (!empty($Fixed)) {
                        $fx = 1;
                        foreach ($Fixed as $fl) {
                      ?>
                          <label class="custom-form form-fixed">
                            <input type="checkbox" id="fixed[]" name="fixed[]" value="<?php echo $fl->fixed_id; ?>">
                            <span class="label-text"><?php echo $fl->fixed_details; ?></span>
                          </label>
                        <?php
                          $fx++;
                        }
                        ?>
                        <input type="hidden" class="form-check-input" name="fixed_count" value="<?php echo $fx; ?>">
                        <label class="custom-form">
                          <input type="checkbox" id="fixed_etc" name="fixed_etc" value="fixed_etc">
                          <span class="label-text">อื่นๆ</span>
                        </label>
                        <input type="text" class="form-control" id="detailFixedOther" name="detailFixedOther">
                      <?php
                      }
                      ?>
                    </div>

                  </div>

                  <div class="col-sm-12 col-lg-6">
                    <div class="form-group">
                      <label for="role">Created by/พนักงานผู้รับสินค้า<span class="remark">*</span></label><BR>
                      <input type="text" class="form-control required" id="create_by_user" name="create_by_user" maxlength="100">
                    </div>
                  </div>

                </div>


    </form>
    <div class="row">
      <div class="col-md-12">
        <form id="upload" method="post" action="<?php echo base_url() ?>/order/do_upload_multi/<?php echo $times; ?>" enctype="multipart/form-data">
          <div id="drop" style="display: -webkit-inline-box; text-align: left; width: 100%;">
            <a><i class="fa fa-camera"></i> ADD IMAGE</a>
            <input type="file" name="upl" accept="image/*" />
          </div>

          <ul>
            <!-- The file uploads will be shown here -->
          </ul>

        </form>

      </div>
    </div>


  </div>
  <div class="box-footer">
    <input type="button" class="btn btn-primary" value="Submit" id="send_order_new" />
    <input type="reset" class="btn btn-default" value="Reset" />
  </div>
</div>
</div>
<!-- <div class="col-sm-12 col-lg-6"> -->

<?php
$this->load->helper('form');
$error = $this->session->flashdata('error');
if ($error) {
?>
  <div class="alert alert-danger alert-dismissable">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
    <?php echo $this->session->flashdata('error'); ?>
  </div>
<?php } ?>
<?php
$success = $this->session->flashdata('success');
if ($success) {
?>
  <div class="modal fade in" id="myModal" tabindex="-1" role="dialog" aria-labelledby="exampleModal" aria-hidden="true" style="display: block; padding-right: 16px;">
    <div class="modal-dialog" role="document">
      <div class="modal-content">

        <div class="modal-body">
          <h3 style="text-align: left; line-height: 1; font-size: 1.5em; color: #7b7b7b;">สร้างข้อมูลเรียบร้อย</h3>
          <div class="" style="text-align: left; line-height: 1; font-size: 1.2em; color: #7b7b7b;">
            <?php echo $this->session->flashdata('success'); ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn custom-btn" data-dismiss="modal" id="close">ตกลง</button>
        </div>
      </div>
    </div>
  </div>
  <div class="alert alert-success alert-dismissable">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
    <?php echo $this->session->flashdata('success'); ?>
  </div>

<?php } ?>

<div class="row">
  <div class="col-md-12">
    <?php echo validation_errors('<div class="alert alert-danger alert-dismissable">', ' <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>'); ?>
  </div>
</div>
</div>
</section>
</div>
</div>

<?php
if (!empty($BranchID) and $BranchID != 0) {
?>
  <script src="<?php echo base_url(); ?>assets/js/addOrder.js" type="text/javascript"></script>
<?php
} else {
?>
  <script src="<?php echo base_url(); ?>assets/js/admin_addOrder.js" type="text/javascript"></script>
<?php
}
?>
<script type="text/javascript">
  var request = null;

  function h_recommend_createXMLRequest() {
    try {
      request = new XMLHttpRequest();
    } catch (trymicrosoft) {
      try {
        request = new ActiveXObject("Msxm12.XMLHTTP");
      } catch (othermicrosoft) {
        try {
          request = new ActiveXObject("Microsoft.XMLHTTP");
        } catch (failed) {
          request = null;
        }
      }
    }

    if (request == null) {
      alert("Error creating request object!");
    }
  }

  function list_recommend_do_ajax(id) {
    h_recommend_createXMLRequest();

    var url = "<?php echo base_url(); ?>user/get_list_branch/" + id;

    request.open("GET", url, true);
    request.onreadystatechange = view_recommend_update;
    request.send(null);

    $('#branch_type').blur();
    $('#branchshort').val('');
  }

  function view_recommend_update() {
    if (request.readyState == 4) {
      document.getElementById('department_one').innerHTML = request.responseText;
    }
  }

  function list_recommend_do_ajax_book(id) {
    h_recommend_createXMLRequest();

    var url = "<?php echo base_url(); ?>user/get_list_book/" + id;

    request.open("GET", url, true);
    request.onreadystatechange = view_recommend_update_book;
    request.send(null);
  }

  function view_recommend_update_book() {
    if (request.readyState == 4) {
      document.getElementById('department_two').innerHTML = request.responseText;
    }
  }

  function list_recommend_do_ajax_branchshort(id) {
    h_recommend_createXMLRequest();

    var url = "<?php echo base_url(); ?>user/get_list_branchshort/" + id;

    request.open("GET", url, true);
    request.onreadystatechange = view_recommend_update_branchshort;
    request.send(null);

    $('#branch_id').blur();
  }

  function view_recommend_update_branchshort() {
    if (request.readyState == 4) {
      document.getElementById('department_branchshort').innerHTML = request.responseText;
    } else {
      $('#branchshort').val('');
    }
  }

  function list_recommend_do_ajax_bookshort(id) {
    h_recommend_createXMLRequest();

    var url = "<?php echo base_url(); ?>user/get_list_bookshort/" + id;

    request.open("GET", url, true);
    request.onreadystatechange = view_recommend_update_bookshort;
    request.send(null);
  }

  function view_recommend_update_bookshort() {
    if (request.readyState == 4) {
      document.getElementById('department_tree').innerHTML = request.responseText;
    }
  }

  $(function() {
    $('#fixed_etc').on('click', function(event) {
      var fixed_etc = $('#fixed_etc:checked').val();

      if (fixed_etc == "fixed_etc") {
        $("#detailFixedOther").css("display", "block");
      } else {
        $("#detailFixedOther").css("display", "none");
      }
    });

    $('#condition_etc').on('click', function(event) {
      var condition_etc = $('#condition_etc:checked').val();

      if (condition_etc == "condition_etc") {
        $("#detailConditionOther").css("display", "block");
      } else {
        $("#detailConditionOther").css("display", "none");
      }
    });

    $('#estimateprice_etc').on('click', function(event) {
      var estimateprice_etc = $('#estimateprice_etc:checked').val();

      if (estimateprice_etc == "estimateprice_etc") {
        $("#detailEstimatePriceOther").css("display", "block");
      } else {
        $("#detailEstimatePriceOther").css("display", "none");
      }
    });

    $('input[name^="pages_title"]').each(function() {
      alert($(this).val());
    });
  });

  $(function() {
    var today = new Date();
    var dateBefore = null;

    $("#requestDate").datepicker({
      dateFormat: 'dd/mm/yy',
      endDate: "today",
      maxDate: today,
      disabled: true,
      //showOn: 'button',
      //      buttonImage: 'http://jqueryui.com/demos/datepicker/images/calendar.gif',
      buttonImageOnly: false,
      //dayNamesMin: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
      //monthNamesShort: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],
      changeMonth: true,
      changeYear: true,
      yearRange: "-100:-0",
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
    var today = new Date();
    var dateBefore = null;

    $("#detailDatePurchase").datepicker({
      dateFormat: 'dd/mm/yy',
      endDate: "today",
      maxDate: today,
      //showOn: 'button',
      //      buttonImage: 'http://jqueryui.com/demos/datepicker/images/calendar.gif',
      buttonImageOnly: false,
      //dayNamesMin: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
      //monthNamesShort: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],
      changeMonth: true,
      changeYear: true,
      yearRange: "-100:-0",
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
          // arrayDate[2]=parseInt(arrayDate[2])+543;
          arrayDate[2] = parseInt(arrayDate[2]);
          $(this).val(arrayDate[0] + "/" + arrayDate[1] + "/" + arrayDate[2]);
        }
      },
      onSelect: function(dateText, inst) {
        dateBefore = $(this).val();
        var arrayDate = dateText.split("/");
        // arrayDate[2]=parseInt(arrayDate[2])+543;
        arrayDate[2] = parseInt(arrayDate[2]);
        $(this).val(arrayDate[0] + "/" + arrayDate[1] + "/" + arrayDate[2]);
      }
    });
  });

  $('#warantyType_1').click(function() {
    $("#detailNumberWaranty_id").css("display", "none");
  });

  $('#close').click(function() {
    window.location.href = "<?php echo base_url(); ?>ordersListing";
  });

  $('#warantyType_2').click(function() {
    $("#detailNumberWaranty_id").css("display", "block");
  });

  $("#send_order_new").click(function() {
    var data_m_txt = "";
    var times = document.getElementById('times').value;

    $("input[name=fiel_name]").each(function(i, val) {
      var data_m = $(this).val();
      var temp_mm = times + data_m;
      data_m_txt += '<input type="hidden" name="file_name_image[]" value="' + temp_mm + '">';
    });

    $('#images_tump_name').html(data_m_txt);
    var BranchID = "<?php echo $BranchID; ?>";

    if (BranchID == "" || BranchID == 0) {
      var branch_type = document.getElementById('branch_type').value;
      var branch_id = document.getElementById('branch_id').value;
      var branchshort = document.getElementById('branchshort').value;

      if (branch_type == 0) {
        window.alert('กรุณาใส่ BRANCH TYPE/ประเภทของสาขา');
        return false;
      }

      if (branch_id == 0) {
        window.alert('กรุณาใส่ BRANCH/สาขา');
        return false;
      }

      if (branchshort == "") {
        window.alert('กรุณาใส่ BRANCH SHORT/ตัวย่อสาขา');
        return false;
      }
    }

    var requestDate = document.getElementById('requestDate').value;
    var bookshort = document.getElementById('bookshort').value;
    var numberID = document.getElementById('numberID').value;
    var orderIDShow = bookshort + '/' + numberID;
    var customerFullname = document.getElementById('customerFullname').value;
    var customerTel = document.getElementById('customerTel').value;
    var detailBrandId = document.getElementById('detailBrandId').value;
    var detailTypeId = document.getElementById('detailTypeId').value;
    var detailSKUName = document.getElementById('detailSKUName').value;
    var create_by_user = document.getElementById('create_by_user').value;

    // if (requestDate == "") {
    //   window.alert('กรุณาใส่ REQUEST DATE/วันที่ส่งซ่อม');
    //   return false;
    // }

    if (bookshort == "") {
      window.alert('กรุณาใส่ BOOK SHORT/เล่มที่ ');
      return false;
    }

    if (numberID == "") {
      window.alert('กรุณาใส่ NUMBER ID/เลขที่ ');
      return false;
    }

    if (customerFullname == "") {
      window.alert('กรุณาใส่ CUSTOMER FULLNAME/ชื่อลูกค้า ');
      return false;
    }

    if (customerTel == "") {
      window.alert('กรุณาใส่ MOBILE TEL/เบอร์มือถือลูกค้า ');
      return false;
    }

    if (customerTel.length !== 10) {
      window.alert('กรุณาตรวจสอบข้อมูล MOBILE TEL/เบอร์มือถือลูกค้า');
      return false;
    }

    if (detailBrandId == 0) {
      window.alert('กรุณาใส่ BRAND ID/ยี่ห้อ');
      return false;
    }

    if (detailTypeId == 0) {
      window.alert('กรุณาใส่ CATEGORY/ประเภท');
      return false;
    }

    if (detailSKUName == "") {
      window.alert('กรุณาใส่ SKU NAME/ชื่อสินค้า');
      return false;
    }

    if ($('input[name^=condition]:checked').length <= 0) {
      alert("กรุณาใส่ CONDITION/อาการที่ส่งซ่อม");
      return false;
    }

    if ($('input[name^=estimateprice]:checked').length <= 0) {
      alert("กรุณาใส่ ESTIMATE PRICE/ประเมินราคาส่งซ่อม");
      return false;
    }

    if ($('input[name^=fixed]:checked').length <= 0) {
      alert("กรุณาใส่ FIXED/สภาพ");
      return false;
    }

    if (create_by_user == "") {
      alert("กรุณาใส่ Created by/พนักงานผู้รับสินค้า");
      return false;
    }

    $.post("<?php echo base_url(); ?>order/get_orderIDShow", {
        orderIDShow: orderIDShow,
        customerTel: customerTel
      })
      .done(function(data) {
        if (data == 1) {
          alert("เล่มที่/เลขที และ เบอร์มือถือลูกค้า มีอยู่แล้ว");
          return false;
        } else {
          setTimeout(function() {
            $("#addOrder").submit();
          }, 1000);
        }
      });
  });

  document.addEventListener('DOMContentLoaded', function() {
    $('#detailBrandId').on('change', function() {
      $(this).blur();
    });

    $('#detailTypeId').on('change', function() {
      $(this).blur();
    });
  });

  var site_url = "<?php echo base_url(); ?>";
  var xtimesite = "<?php echo $times; ?>";
</script>