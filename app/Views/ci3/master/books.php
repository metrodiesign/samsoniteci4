<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-table-form">
      <section class="content-header">
        <div class="row">
            <div class="col-xs-6">
               <h1>
                 <i class="fa fa-link"></i> Book Management
                 <small>Add, Edit, Delete</small>
               </h1>
            </div>
             <div class="col-xs-6 text-right">
                <div class="form-group">
                     <a class="btn btn-primary" href="<?php echo base_url(); ?>BookNew"><i class="fa fa-plus"></i> Add New</a>
                </div>
             </div>
        </div>
      </section>
      <section class="content">

          <div class="row">
               <div class="col-xs-12">
                 <div class="box">
                  <div class="box-header">
                       <h3 class="box-title">Book List</h3>
                       <div class="box-tools">
                           <form action="<?php echo base_url() ?>bookListing" method="POST" id="searchList">
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
                         <th>ฺBookId</th>
                         <th>Branch name</th>
                         <th>Book Details</th>
                         <th>Status</th>
                         <th class="text-center">Actions</th>
                       </tr>
                       <?php
                       if(!empty($bookRecords))
                       {
                           foreach($bookRecords as $record)
                           {
                             $status=$record->status;
                             if($status==1){
                                 $status_detail="Publishing";
                             }else{
                                 $status_detail="Unpublish";
                             }
                       ?>
                       <tr>
                         <td><?php echo $record->book_id ?></td>
                         <td><?php echo $record->branch_name ?></td>
                         <td><?php echo $record->book_detail ?></td>
                           <td><?php echo $status_detail ?></td>
                         <td class="text-center">

                             <a class="btn btn-sm btn-info" href="<?php echo base_url().'editBookOld/'.$record->book_id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
                             <a class="btn btn-sm btn-danger deleteBook" href="#" data-bookid="<?php echo $record->book_id; ?>" title="Delete"><i class="fa fa-trash"></i></a>
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

	jQuery(document).on("click", ".deleteBook", function(){
      var b="<?php echo base_url(); ?>";
		var bookid = $(this).data("bookid"),
			hitURL = b + "deleteBook",
			currentRow = $(this);
      console.log(bookid);
//alert(branchid);
		var confirmation = confirm("Are you sure to delete this  ? ");

		if(confirmation)
		{
			jQuery.ajax({
			type : "POST",
			dataType : "json",
			url : hitURL,
			data : { bookid : bookid }
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
        jQuery("#searchList").attr("action", baseURL + "bookListing/" + value);
        jQuery("#searchList").submit();
    });
});
</script>
