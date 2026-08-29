<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <!-- <div class="banner-cms">
     <div class="background-img" style="background-image: url(<?php echo base_url().$branch_type_image; ?>)"></div>
   </div> -->
   <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
   <div class="content-form">
     <section class="content-header">
        <div class="row">
           <div class="col-xs-8">
              <h1>
                <i class="fa fa-cart-arrow-down"></i> KPI
              </h1>
           </div>
           <div class="col-xs-4">
             <div class="pull-right">
               <a href="#" class="btn btn-primary"  id="btnGuru"> Export</a>
             </div>
           </div>
        </div>
     </section>
     <!-- <div class="content-dashbord"> -->

        <section class="content">
            <div class="row">
              <div class="col-md-12">
                <div class="box box-primary">
                  <div class="box-header">
                    <div class="col-sm-12 col-lg-3">
                      <h3 class="box-title">KPI Report</h3>
                    </div>
                    <div class="col-sm-12 col-lg-8">
                      <div id="report_track" class="box-tools">
                          <form action="<?php echo base_url() ?>user/report_total_job_pending" method="POST" id="searchList">
                              <div class="input-group">
                                <div class="pull-right box-saerch">
                                  <?php
                                    $BID=$this->session->userdata('BranchID');
                                    if($BID){
                                      ?>
                                      <input type="hidden" name="branch_id" value="<?php echo $BID; ?>" id="branch_id" class="input-saerch" />
                                      <?php
                                    }else{
                                   ?>
                                  <div class="box-saerch-sub">
                                    <label for="[object Object]">Branch :</label>

                                    <select id="branch_id" name="branch_id" style="width: 120px;" >
                                         <option value="0">ALL</option>
                                         <?php
                                         if(!empty($brans_list))
                                         {
                                           //var_dum($Providers);
                                             foreach ($brans_list as $rl)
                                             {
                                                 ?>
                                                 <option value="<?php echo $rl->branch_id ?>" <?php if($rl->branch_id == set_value('branch_id')) {echo "selected=selected";} ?>><?php echo $rl->branch_name.','.$rl->branch_user_name ?></option>
                                                 <?php
                                             }
                                         }
                                         ?>
                                    </select>

                                  </div>
                                  <?php
                                      }
                                   ?>
                                  <div class="box-saerch-sub">
                                    <label for="[object Object]">from Date :</label>
                                    <input type="text" name="start_date" value="<?php echo $start_date; ?>" id="start_date" class="input-saerch" />
                                  </div>
                                  <div class="box-saerch-sub">
                                    <label for="[object Object]">To Date : </label>
                                    <input type="text" name="end_date" value="<?php echo $end_date; ?>" id="end_date"  class="input-saerch" />
                                  </div>
                                  <div class="box-saerch-sub2">
                                    <button class="btn btn-sm btn-default searchList input-saerch-btn"><i class="fa fa-search"></i></button>
                                  </div>

                                </div>
                              </div>
                          </form>
                      </div>
                    </div>
                   </div>
                   <?php
                  // if($GroupID !=3)
                   //{
                   ?>
                   <div class="row">
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
                                 <table id="myDataTable" class="table table-striped table-bordered" style="width:100%" >
                                   <tr>
                                     <th class="text-center">No</th>
                                     <th class="text-center">Detail</th>
                                     <th class="text-center">Job</th>
                                     <th class="text-center">Average (Percent)</th>
                                   </tr>
                                   <?php
                                      $Total_result_a=($pending_Neworder+$pending_Complete+$pending_Aftercomplete);
                                      if($pending_Neworder>0){
                                        $Total_p_result_a=(($pending_Neworder*100)/$Total_result_a);
                                      }else{
                                        $Total_p_result_a=0;
                                      }

                                      if($pending_Complete>0){
                                        $Total_p_result_b=(($pending_Complete*100)/$Total_result_a);
                                      }else{
                                        $Total_p_result_b=0;
                                      }
                                      if($pending_Aftercomplete>0){
                                        $Total_p_result_c=(($pending_Aftercomplete*100)/$Total_result_a);
                                      }else{
                                        $Total_p_result_c=0;
                                      }
                                      $Total_p_result=$Total_p_result_a+$Total_p_result_b+$Total_p_result_c;
                                    ?>
                                   <tr>
                                     <td align="center">1</td>
                                     <td>Waiting for CMG to pick up</td>
                                     <td align="right"><?php echo $pending_Neworder; ?></td>
                                     <td align="right"><?php echo round($Total_p_result_a,2); ?>%</td>
                                   </tr>
                                   <tr>
                                     <td align="center">2</td>
                                     <td>Working in process - CMG</td>
                                     <td align="right"><?php echo $pending_Complete; ?></td>
                                     <td align="right"><?php echo round($Total_p_result_b,2); ?>%</td>
                                   </tr>
                                   <tr>
                                     <td align="center">3</td>
                                     <td>Pending for customer to pick up </td>
                                     <td align="right"><?php echo $pending_Aftercomplete; ?></td>
                                     <td align="right"><?php echo round($Total_p_result_c,2); ?>%</td>
                                   </tr>

                                   <tr>
                                     <td colspan="2">TOTAL</td>
                                     <td align="right"><?php echo number_format($Total_result_a,0); ?></td>
                                     <td align="right"><?php echo number_format($Total_p_result,0); ?>%</td>
                                   </tr>

                                 </table>

                              </div>

                            </div>
                          </div>
                     </div>


                   <?php
                // }else{
                  ?>



                 </div>
              </div>




          </div>
        </section>

     <!-- </div> -->
   </div>


</div>
<script type="text/javascript">
function search_status()
{
  $( "#searchList" ).submit();
}
$(function(){
    var dateBefore=null;
    $("#start_date").datepicker({
      dateFormat: 'dd/mm/yy',
      //showOn: 'button',
//      buttonImage: 'http://jqueryui.com/demos/datepicker/images/calendar.gif',
      buttonImageOnly: false,
      //dayNamesMin: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
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
$(function(){
    var dateBefore=null;
    $("#end_date").datepicker({
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
$("#btnGuru").click(function () {
    //tableToExcel('myDataTable', 'W3C Example Table');
    fnExcelReport('myDataTable', 'total_job_pending');
});


function fnExcelReport(id, name) {
  var tab_text = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    tab_text = tab_text + '<head><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    tab_text = tab_text + '<x:Name>Test Sheet</x:Name>';
    tab_text = tab_text + '<x:WorksheetOptions><x:Panes></x:Panes></x:WorksheetOptions></x:ExcelWorksheet>';
    tab_text = tab_text + '</x:ExcelWorksheets></x:ExcelWorkbook></xml></head><body>';
    tab_text = tab_text + "<table border='1px'>";
    var exportTable = $('#' + id).clone();
    exportTable.find('input').each(function (index, elem) { $(elem).remove(); });
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
        var blob2 =  new Blob([tab_text], {
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
window.onload = function () {
var t1="<?php echo round($Total_p_result_a,2); ?>";
var t2="<?php echo round($Total_p_result_b,2); ?>";
var t3="<?php echo round($Total_p_result_c,2); ?>";
var options = {
	title: {
		text: "Pending Job"
	},
	subtitles: [{
		text: "<?php echo $start_date; ?> - <?php echo $end_date; ?>"
	}],
	animationEnabled: true,
	data: [{
    type: "pie",
			startAngle: 45,
			showInLegend: "true",
			legendText: "{label}",
			indexLabel: "{label} ({y})%",
			yValueFormatString:"#,##0.#"%"",
		dataPoints: [
			{ y: t1, label: "Waiting for CMG to pick up" },
			{ y: t2, label: "Working in process - CMG" },
			{ y: t3, label: "Pending for customer to pick up" }
		]
	}]
};
$("#chartContainer").CanvasJSChart(options);

}
</script>

<script src="<?php echo base_url(); ?>assets/dist/js/jquery.canvasjs.min.js"></script>
