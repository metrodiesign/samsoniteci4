<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-table-form">
      <section class="content-header">
        <h1>
          <i class="fa fa-cart-arrow-down"></i> TRANSPORTING
        </h1>
      </section>
      <section class="content">

          <div class="row">
               <div class="col-xs-12">
                 <div class="box box-scroll">
                  <div class="box-header">
                    <div class="col-sm-12 col-lg-6">
                        <h3 class="box-title">TRANSPORTING List</h3>
                    </div>
                    <div class="col-sm-12 col-lg-6">
                      <div class="box-tools">
                          <form action="<?php echo base_url() ?>TrackingListing" method="POST" id="searchList">
                              <div class="input-group">
                                <div class="pull-right box-saerch">
                                  <div class="box-saerch-sub">
                                    <label for="[object Object]">Date :</label>
                                    <input type="text" name="sdate" value="<?php echo $sdate; ?>" id="sdate" class="input-saerch" placeholder="Date"/>
                                  </div>
                                  <div class="box-saerch-sub2">
                                    <label for="[object Object]">Detail : </label>
                                     <input type="text" name="searchText" value="<?php echo $searchText; ?>" class="form-control input-sm pull-right input-saerch" placeholder="Search"/>
                                    <button class="btn btn-sm btn-default searchList input-saerch-btn2"><i class="fa fa-search"></i></button>
                                  </div>

                                </div>
                              </div>
                          </form>
                      </div>
                    </div>
                  </div><!-- /.box-header -->
                     <form action="<?php echo base_url() ?>sendorderUpdateStatus" method="POST" id="sendorderUpdateStatus">
                  <div class="box-body table-responsive no-padding">
                     <table class="table table-hover">
                       <tr>
                         <th>Id</th>

                         <th>trackID</th>
                         <th>orderID</th>
                         <th>Fullname</th>
                         <th>Tel</th>
                         <th>Email</th>
                         <th>RequestDate</th>
                         <th>Action status</th>
                         <th class="text-center">status Update</th>
                         <th class="text-center">Actions <BR>
                            &nbsp;Select ALL tracking<br>
                            <div class="form-group" style="margin-bottom: 0px;">
                                <label for="detailAgent"></label>
                                   <label class="custom-form">
                     						<input type="checkbox"  id="selectall_tracking" name="selectall_tracking"/>
                                       <span class="label-text"></span>
                  				      </label>
                                <!-- <input type="checkbox" class="form-check-input" id="detailAgent" name="detailAgent" > -->
                            </div>
                         </th>
                       </tr>
                       <?php
                       if(!empty($OrdersRecords))
                       {
                         if($page==0){
                           $i=0;
                         }else{
                           $i=$page;
                         }
                           foreach($OrdersRecords as $record)
                           {
                             $i++;
                             $dd=$record->requestDate;
                             $AA=substr($dd,0,4);
                             $BB=substr($dd,5,2);
                             $CC=substr($dd,8,2);
                             $DD=$AA;
                             $XX=$CC."/".$BB."/".$DD;
                             $trackID=$record->orderID;
                             $customerTel=$record->customerTel;
                             $ststus_update=$this->request_order_model->chack_status_update($trackID,$customerTel);
                       ?>
                       <tr>
                         <td><?php echo $i ?></td>
                         <td><?php echo $record->trackID ?></td>
                         <td><?php echo $record->orderIDShow ?></td>
                         <td><?php echo $record->customerFullname ?></td>
                         <td><?php echo $record->customerTel ?></td>
                         <td><?php echo $record->customerEmail ?></td>
                         <td><?php echo $XX ?></td>
                         <td><?php echo $record->status_name ?></td>
                         <td align="center">
                           <?php echo $ststus_update;?>
                         </td>

                         <td class="text-center">
                            <div class="form-group" style="margin-bottom: 0px;">
                                <label for="detailAgent"></label>
                                   <label class="custom-form">
                     						<input type="checkbox" id="select_list_id[]" name="select_list_id[]" value="<?php echo $record->request_id ?>">
                                       <span class="label-text"></span>
                  				      </label>
                                <!-- <input type="checkbox" class="form-check-input" id="detailAgent" name="detailAgent" > -->
                            </div>

                        </td>

                         </td>
                       </tr>
                       <?php
                           }
                       }
                       ?>
                     </table>

                  </div><!-- /.box-body -->
                     <div class="box-footer">
                  &nbsp; &nbsp;status&nbsp; &nbsp;
                  <select id="status_id" name="status_id" style="width: 200px;" >
                       <option value="0">Select status</option>
                       <?php
                       if(!empty($Status))
                       {
                         //var_dum($Providers);
                           foreach ($Status as $rl)
                           {
                             if($rl->status_id >2 and $rl->status_id <5){
                               ?>
                               <option value="<?php echo $rl->status_id ?>" <?php if($rl->status_id == set_value('status_id')) {echo "selected=selected";} ?>><?php echo $rl->status_name ?></option>
                               <?php
                             }
                           }
                       }
                       ?>
                  </select>

                  &nbsp; &nbsp; <button name="submit_list" type="submit" class="btn btn-primary">Send</button>
                  </form>
                 </div>
                 <?php
                     $this->load->helper('form');
                     $error = $this->session->flashdata('error');
                     if($error)
                     {
                 ?>
                 <div class="alert alert-danger alert-dismissable">
                     <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                     <?php echo $this->session->flashdata('error'); ?>
                 </div>
                 <?php } ?>
                 <?php
                     $success = $this->session->flashdata('success');
                     if($success)
                     {
                 ?>
                 <div class="alert alert-success alert-dismissable">
                     <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                     <?php echo $this->session->flashdata('success'); ?>
                 </div>
                 <?php } ?>
                  <div class="box-footer clearfix">
                       <?php echo $this->pagination->create_links(); ?>
                  </div>
                 </div><!-- /.box -->
               </div>
          </div>
      </section>
    </div>

</div>

<script type="text/javascript">
jQuery(document).ready(function(){

	jQuery(document).on("click", ".deleteOrders", function(){
		var branchId = $(this).data("orderid"),
			hitURL = baseURL + "deleteOrders",
			currentRow = $(this);
      console.log(orderid);
//alert(branchid);
		var confirmation = confirm("Are you sure to delete this  ? ");

		if(confirmation)
		{
			jQuery.ajax({
			type : "POST",
			dataType : "json",
			url : hitURL,
			data : { orderid : orderid }
			}).done(function(data){
				console.log(data);
				currentRow.parents('tr').remove();
				if(data.status = true) { alert("branch successfully deleted"); }
				else if(data.status = false) { alert("branch deletion failed"); }
				else { alert("Access denied..!"); }
			});
		}
	});


	jQuery(document).on("click", ".searchList", function(){

	});

});
jQuery(document).ready(function(){
    jQuery('ul.pagination li a').click(function (e) {
        e.preventDefault();
        var link = jQuery(this).get(0).href;
        var value = link.substring(link.lastIndexOf('/') + 1);
        jQuery("#searchList").attr("action", baseURL + "TrackingListing/" + value);
        jQuery("#searchList").submit();
    });



        jQuery("#selectall_tracking").click(function () {
             //var selectall =  document.getElementsByName("selectall_tracking").value;
           var statusNum=chkb($("#selectall_tracking").is(':checked'));

          var chk_arr =  document.getElementsByName("select_list_id[]");
          var chklength = chk_arr.length;
          if(statusNum==1){
            for(k=0;k< chklength;k++)
            {
              chk_arr[k].checked = true;
            }
          }else{
            for(k=0;k< chklength;k++)
            {
              chk_arr[k].checked = false;
            }
          }

          });
    });
    function chkb(bool){
    	if(bool)
    	return 1;
    	return 0;
    }

</script>
<script type="text/javascript">
$(function(){
    var dateBefore=null;
    $("#sdate").datepicker({
      dateFormat: 'dd/mm/yy',
      //showOn: 'button',
//      buttonImage: 'http://jqueryui.com/demos/datepicker/images/calendar.gif',
      buttonImageOnly: false,
    //  dayNamesMin: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
    //  monthNamesShort: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],
      changeMonth: true,
      changeYear: true,
      beforeShow:function(){
          if($(this).val()!=""){
              var arrayDate=$(this).val().split("/");
              arrayDate[2]=parseInt(arrayDate[2]);
              $(this).val(arrayDate[0]+"/"+arrayDate[1]+"/"+arrayDate[2]);
          }
          setTimeout(function(){
              $.each($(".ui-datepicker-year option"),function(j,k){
                  var textYear=parseInt($(".ui-datepicker-year option").eq(j).val());
                  $(".ui-datepicker-year option").eq(j).text(textYear);
              });
          },50);
      },
      onChangeMonthYear: function(){
          setTimeout(function(){
              $.each($(".ui-datepicker-year option"),function(j,k){
                  var textYear=parseInt($(".ui-datepicker-year option").eq(j).val());
                  $(".ui-datepicker-year option").eq(j).text(textYear);
              });
          },50);
      },
      onClose:function(){
          if($(this).val()!="" && $(this).val()==dateBefore){
              var arrayDate=dateBefore.split("/");
              arrayDate[2]=parseInt(arrayDate[2]);
              $(this).val(arrayDate[0]+"/"+arrayDate[1]+"/"+arrayDate[2]);
          }
      },
      onSelect: function(dateText, inst){
          dateBefore=$(this).val();
          var arrayDate=dateText.split("/");
          arrayDate[2]=parseInt(arrayDate[2]);
          $(this).val(arrayDate[0]+"/"+arrayDate[1]+"/"+arrayDate[2]);
      }

    });

});

</script>
