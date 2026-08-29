<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-table-form">
      <section class="content-header">
        <div class="row">
          <div class="col-xs-6">
             <h1>
               <i class="fa fa-link"></i> Brand Management
               <small>Add, Edit, Delete</small>
             </h1>
          </div>
          <div class="col-xs-6 text-right">
             <div class="form-group">
                  <a class="btn btn-primary" href="<?php echo base_url(); ?>add_new_brand"><i class="fa fa-plus"></i> Add New</a>
             </div>
          </div>
        </div>
      </section>
      <section class="content">

          <div class="row">
               <div class="col-xs-12">
                 <div class="box">
                  <div class="box-header">
                       <h3 class="box-title">Brand List</h3>
                       <div class="box-tools">
                           <form action="<?php echo base_url() ?>brandListing" method="POST" id="searchList">
                               <div class="input-group">
                                 <input type="text" name="searchText" value="<?php echo $searchText; ?>" class="form-control input-sm pull-right" style="width: 150px;" placeholder="Search"/>
                                 <div class="input-group-btn">
                                   <button class="btn btn-sm btn-default searchList"><i class="fa fa-search"></i></button>
                                 </div>
                               </div>
                           </form>
                       </div>
                  </div><!-- /.box-header -->
                  <div class="box-body table-responsive no-padding">
                     <table class="table table-hover">
                       <tr>
                         <th>Brand Id</th>
                         <th>Brand Details</th>
                         <th class="text-center">Actions</th>
                       </tr>
                       <?php
                       if(!empty($brandRecords))
                       {
                           foreach($brandRecords as $record)
                           {
                       ?>
                       <tr>
                         <td><?php echo $record->brand_id ?></td>
                         <td><?php echo $record->brand_details ?></td>

                         <td class="text-center">

                             <a class="btn btn-sm btn-info" href="<?php echo base_url().'editBrandOld/'.$record->brand_id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
                             <a class="btn btn-sm btn-danger deleteBrand" href="#" data-brandid="<?php echo $record->brand_id; ?>" title="Delete"><i class="fa fa-trash"></i></a>
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

	jQuery(document).on("click", ".deleteBrand", function(){
    var b="<?php echo base_url(); ?>";
		var brandid = $(this).data("brandid"),
			hitURL =b+"deleteBrand",
			currentRow = $(this);
      console.log(brandid);
//alert(hitURL);
		var confirmation = confirm("Are you sure to delete this  ? ");

		if(confirmation)
		{
			jQuery.ajax({
			type : "POST",
			dataType : "json",
			url : hitURL,
			data : {brandid:brandid }
			}).done(function(data){
				console.log(data);
				currentRow.parents('tr').remove();
				if(data.status = true) { alert("brand successfully deleted"); }
				else if(data.status = false) { alert("brand deletion failed"); }
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
        jQuery("#searchList").attr("action", baseURL + "brandListing/" + value);
        jQuery("#searchList").submit();
    });
});
</script>
