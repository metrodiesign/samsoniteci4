<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-table-form">
      <section class="content-header">
        <div class="row">
            <div class="col-xs-12">
               <h1>
                 <i class="fa fa-link"></i> Contact Management
               </h1>
            </div>
        </div>
      </section>
      <section class="content">

          <div class="row">
              <div class="col-xs-12">
                <div class="box box-scroll">
                  <div class="box-header">
                      <h3 class="box-title">Contact List</h3>
                      <div class="box-tools">
                          <form action="<?php echo base_url() ?>contactListing" method="POST" id="searchList">
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
                        <th>Id</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Samsoniteid</th>
                        <th>Phone</th>
                        <th>Detail</th>
                        <th>Date</th>
                      </tr>
                      <?php
                      if(!empty($contactRecords))
                      {
                          foreach($contactRecords as $record)
                          {
                      ?>
                      <tr>
                        <td><?php echo $record->id ?></td>
                        <td><?php echo $record->fullname ?></td>
                        <td><?php echo $record->email ?></td>
                        <td><?php echo $record->samsoniteid ?></td>
                        <td><?php echo $record->phone ?></td>
                        <td><?php echo $record->detail ?></td>
                        <td><?php echo $record->cdate ?></td>
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

	jQuery(document).on("click", ".deleteBranch", function(){
      var b="<?php echo base_url(); ?>";
		var branchId = $(this).data("branchid"),
			hitURL = b + "deleteBranch",
			currentRow = $(this);
      console.log(branchId);
//alert(branchid);
		var confirmation = confirm("Are you sure to delete this  ? ");

		if(confirmation)
		{
			jQuery.ajax({
			type : "POST",
			dataType : "json",
			url : hitURL,
			data : { branchid : branchId }
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
        jQuery("#searchList").attr("action", baseURL + "contactListing/" + value);
        jQuery("#searchList").submit();
    });
});
</script>
