<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-table-form">
      <section class="content-header">
        <div class="row">
           <div class="col-xs-8">
             <h1>
               <i class="fa fa-cart-arrow-down"></i> NEW REQUEST REPAIR<br>
               <small>Add, Edit, Delete</small>
             </h1>
           </div>
             <div class="col-xs-4 text-right">
                <div class="form-group">

                     <a class="btn btn-primary" href="<?php echo base_url(); ?>Orders"><i class="fa fa-plus"></i> Add New</a>
                </div>
             </div>
        </div>
      </section>
      <section class="content">
          <div class="row">
               <div class="col-xs-12">
                 <div class="box box-scroll">
                  <div class="box-header">
                      <div class="col-sm-12 col-lg-5">
                          <h3 class="box-title">Request order List</h3>
                      </div>
                       <div class="col-sm-12 col-lg-7">
                         <div class="box-tools">
                             <form action="<?php echo base_url() ?>ordersListing" method="POST" id="searchList">
                                 <div class="input-group">
                                   <div class="pull-right box-saerch">
                                     <div class="box-saerch-sub">
                                       <label for="[object Object]">from Date :</label>
                                       <input type="text" name="sdate" value="<?php echo $sdate; ?>" id="sdate" class="input-saerch" placeholder="Date"/>
                                     </div>
                                     <div class="box-saerch-sub">
                                       <label for="[object Object]">To Date : </label>
                                       <input type="text" name="edate" value="<?php echo $edate; ?>" id="edate"  class="input-saerch" placeholder="Date"/>
                                     </div>
                                     <div class="box-saerch-sub2">
                                       <label for="[object Object]">Detail : </label>
                                        <input type="text" name="searchText" value="<?php echo $searchText; ?>" class="form-control input-sm pull-right input-saerch" placeholder="Search"/>
                                       <button class="btn btn-sm btn-default searchList input-saerch-btn"><i class="fa fa-search"></i></button>
                                     </div>

                                   </div>
                                 </div>
                             </form>
                         </div>
                       </div>

                  </div><!-- /.box-header -->
                  <div class="box-body table-responsive no-padding">
                     <table class="table table-hover">
                       <tr>
                         <th>Id</th>

                         <th>TrackID</th>
                         <th>OrderID</th>
                         <th>Fullname</th>
                         <th>Tel</th>
                         <th>Email</th>
                         <th>RequestDate</th>
                         <th>Action status</th>
                         <th class="text-center">Actions</th>
                       </tr>
                       <?php
                       if(!empty($OrdersRecords))
                       {
                           foreach($OrdersRecords as $record)
                           {
                             $dd=$record->requestDate;
                             $AA=substr($dd,0,4);
                             $BB=substr($dd,5,2);
                             $CC=substr($dd,8,2);
                             // $DD=$AA+543;
                             $DD=$AA;
                             $XX=$CC."/".$BB."/".$DD;
                             $url_print=base_url().'OrderPrint/'.$record->request_id;
                       ?>
                       <tr>
                         <td><?php echo $record->request_id ?></td>
                         <td><?php echo $record->trackID ?></td>
                         <td><?php echo $record->orderIDShow ?></td>
                         <td><?php echo $record->customerFullname ?></td>
                         <td><?php echo $record->customerTel ?></td>
                         <td><?php echo $record->customerEmail ?></td>
                         <td><?php echo $XX ?></td>
                         <td><?php echo $record->status_name ?></td>


                         <td class="text-center">

                             <a class="btn btn-sm btn-info" href="<?php echo base_url().'editOrdersOld/'.$record->request_id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
                              <a class="btn btn-sm btn-danger deleteOrders" href="#" data-order_id="<?php echo $record->request_id; ?>" title="Delete"><i class="fa fa-window-close"></i></a>
                             <a class="btn btn-sm btn-info" onclick="printPreview('<?php echo $url_print;?>')" title="Print"><i class="fa fa-print"></i></a>

                         </td>
                       </tr>
                       <?php
                           }
                       }
                       ?>
                     </table>

                  </div><!-- /.box-body -->
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
      var b="<?php echo base_url(); ?>";
		var order_id = $(this).data("order_id"),
			hitURL = b + "deleteOrders",
			currentRow = $(this);
      console.log(order_id);
//alert(branchid);
		var confirmation = confirm("Are you sure to Delete this  ? ");

		if(confirmation)
		{
			jQuery.ajax({
			type : "POST",
			dataType : "json",
			url : hitURL,
			data : { orderid : order_id }
			}).done(function(data){
				console.log(data);
				currentRow.parents('tr').remove();
				if(data.status = true) { alert("Order successfully Delete"); }
				else if(data.status = false) { alert("Order deletion failed"); }
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
        jQuery("#searchList").attr("action", baseURL + "ordersListing/" + value);
        jQuery("#searchList").submit();
    });
});
</script>
<script type="text/javascript">

$(function(){
    var dateBefore=null;
    $("#sdate").datepicker({
      dateFormat: 'dd/mm/yy',
      //showOn: 'button',
//      buttonImage: 'http://jqueryui.com/demos/datepicker/images/calendar.gif',
      buttonImageOnly: false,
      dayNamesMin: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
      monthNamesShort: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],
      changeMonth: true,
      changeYear: true,
      beforeShow:function(){
          if($(this).val()!=""){
              var arrayDate=$(this).val().split("/");
              // arrayDate[2]=parseInt(arrayDate[2])-543;
              arrayDate[2]=parseInt(arrayDate[2]);
              $(this).val(arrayDate[0]+"/"+arrayDate[1]+"/"+arrayDate[2]);
          }
          setTimeout(function(){
              $.each($(".ui-datepicker-year option"),function(j,k){
                  // var textYear=parseInt($(".ui-datepicker-year option").eq(j).val())+543;
                  var textYear=parseInt($(".ui-datepicker-year option").eq(j).val());
                  $(".ui-datepicker-year option").eq(j).text(textYear);
              });
          },50);
      },
      onChangeMonthYear: function(){
          setTimeout(function(){
              $.each($(".ui-datepicker-year option"),function(j,k){
                  // var textYear=parseInt($(".ui-datepicker-year option").eq(j).val())+543;
                  var textYear=parseInt($(".ui-datepicker-year option").eq(j).val());
                  $(".ui-datepicker-year option").eq(j).text(textYear);
              });
          },50);
      },
      onClose:function(){
          if($(this).val()!="" && $(this).val()==dateBefore){
              var arrayDate=dateBefore.split("/");
              // arrayDate[2]=parseInt(arrayDate[2])+543;
              arrayDate[2]=parseInt(arrayDate[2]);
              $(this).val(arrayDate[0]+"/"+arrayDate[1]+"/"+arrayDate[2]);
          }
      },
      onSelect: function(dateText, inst){
          dateBefore=$(this).val();
          var arrayDate=dateText.split("/");
          // arrayDate[2]=parseInt(arrayDate[2])+543;
          arrayDate[2]=parseInt(arrayDate[2]);
          $(this).val(arrayDate[0]+"/"+arrayDate[1]+"/"+arrayDate[2]);
      }

    });

});
$(function(){
    var dateBefore=null;
    $("#edate").datepicker({
      dateFormat: 'dd/mm/yy',
      //showOn: 'button',
//      buttonImage: 'http://jqueryui.com/demos/datepicker/images/calendar.gif',
      buttonImageOnly: false,
      dayNamesMin: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
      monthNamesShort: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],
      changeMonth: true,
      changeYear: true,
      beforeShow:function(){
          if($(this).val()!=""){
              var arrayDate=$(this).val().split("/");
              // arrayDate[2]=parseInt(arrayDate[2])-543;
              arrayDate[2]=parseInt(arrayDate[2]);
              $(this).val(arrayDate[0]+"/"+arrayDate[1]+"/"+arrayDate[2]);
          }
          setTimeout(function(){
              $.each($(".ui-datepicker-year option"),function(j,k){
                  // var textYear=parseInt($(".ui-datepicker-year option").eq(j).val())+543;
                  var textYear=parseInt($(".ui-datepicker-year option").eq(j).val());
                  $(".ui-datepicker-year option").eq(j).text(textYear);
              });
          },50);
      },
      onChangeMonthYear: function(){
          setTimeout(function(){
              $.each($(".ui-datepicker-year option"),function(j,k){
                  // var textYear=parseInt($(".ui-datepicker-year option").eq(j).val())+543;
                  var textYear=parseInt($(".ui-datepicker-year option").eq(j).val());
                  $(".ui-datepicker-year option").eq(j).text(textYear);
              });
          },50);
      },
      onClose:function(){
          if($(this).val()!="" && $(this).val()==dateBefore){
              var arrayDate=dateBefore.split("/");
              // arrayDate[2]=parseInt(arrayDate[2])+543;
              arrayDate[2]=parseInt(arrayDate[2]);
              $(this).val(arrayDate[0]+"/"+arrayDate[1]+"/"+arrayDate[2]);
          }
      },
      onSelect: function(dateText, inst){
          dateBefore=$(this).val();
          var arrayDate=dateText.split("/");
          // arrayDate[2]=parseInt(arrayDate[2])+543;
          arrayDate[2]=parseInt(arrayDate[2]);
          $(this).val(arrayDate[0]+"/"+arrayDate[1]+"/"+arrayDate[2]);
      }

    });

});
</script>
<script type="text/javascript">
function printPreview(url) {
        var windowWidth=1000;
        var windowHeight=600;
        var myleft=(screen.width)?(screen.width-windowWidth)*0.5:100;
        var mytop=(screen.height)?(screen.height-windowHeight)*0.5:100;
        var feature='left='+myleft+',top='+eval(mytop-50)+',width='+windowWidth+',height='+windowHeight+',';
        feature+='menubar=yes,status=no,location=no,toolbar=no,scrollbars=yes';
        window.open(url,'samsonite',feature);

}
</script>
