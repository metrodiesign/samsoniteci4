<div class="content-wrapper">

    <!-- Content Header (Page header) -->

    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>

    <div class="content-form">

      <section class="content-header">

        <div class="row">

            <div class="col-xs-6">

               <h1>

                 <i class="fa fa-link"></i> background Web EN

                 <small>Edit</small>

               </h1>

            </div>


        </div>

      </section>

      <section class="content">

          <div class="row">

              <div class="col-xs-12">

                <div class="box box-scroll">

                  <div class="box-header">

                      <h3 class="box-title">background web List</h3>



                  </div><!-- /.box-header -->

                  <div class="box-body table-responsive no-padding">

                    <table class="table table-hover">

                      <tr>

                        <th>ฺId</th>

                        <th>Track</th>

                        <th>Tracks tatus</th>

                        <th>Contact</th>

                        <th>Status</th>

                        <th class="text-center">Actions</th>

                      </tr>

                      <?php

                      if(!empty($BackgroundRecords))

                      {

                          foreach($BackgroundRecords as $record)

                          {

                            $status=$record->status;

                            if($status==1){

                                $status_detail="Publishing";

                            }else{

                                $status_detail="Unpublish";

                            }

                      ?>

                      <tr>

                        <td><?php echo $record->id ?></td>

                        <td><img src="<?php echo base_url().$record->image_track_laptop ?>" class="img-fluid" alt="Responsive image"  height="100px"></td>

                        <td><img src="<?php echo base_url().$record->image_trackstatus_laptop ?>" class="img-fluid" alt="Responsive image" height="100px"></td>

                        <td><img src="<?php echo base_url().$record->image_contact_laptop ?>" class="img-fluid" alt="Responsive image" height="100px"></td>

                        <td><?php echo $status_detail ?></td>

                        <td class="text-center">



                            <a class="btn btn-sm btn-info" href="<?php echo base_url().'editBackgroundOld/'.$record->id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>

                        
                        </td>

                      </tr>

                      <?php

                          }

                      }

                      ?>

                    </table>



                  </div><!-- /.box-body -->



                </div><!-- /.box -->

              </div>

          </div>

      </section>

    </div>



</div>



<script type="text/javascript">

jQuery(document).ready(function(){



	jQuery(document).on("click", ".deleteBackground", function(){

      var b="<?php echo base_url(); ?>";

		var Backgroundid = $(this).data("Backgroundid"),

			hitURL = b + "deleteBackground",

			currentRow = $(this);

      console.log(Backgroundid);

//alert(branchid);

		var confirmation = confirm("Are you sure to delete this  ? ");



		if(confirmation)

		{

			jQuery.ajax({

			type : "POST",

			dataType : "json",

			url : hitURL,

			data : { Backgroundid : Backgroundid }

			}).done(function(data){

				console.log(data);

				currentRow.parents('tr').remove();

				if(data.status = true) { alert("Background successfully deleted"); }

				else if(data.status = false) { alert("Background deletion failed"); }

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

        jQuery("#searchList").attr("action", baseURL + "BackgroundListing/" + value);

        jQuery("#searchList").submit();

    });

});

</script>
