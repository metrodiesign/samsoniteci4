<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="background-form" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-form.png);"></div>
    <div class="content-form">
      <section class="content-header">
        <div class="row">
            <div class="col-xs-6">
               <h1>
                 <i class="fa fa-link"></i> Menu Management
                 <small>Add, Edit, Delete</small>
               </h1>
            </div>
             <div class="col-xs-6 text-right">
                <div class="form-group">
                     <a class="btn btn-primary" href="<?php echo base_url(); ?>addNewMenu"><i class="fa fa-plus"></i> Add New</a>
                </div>
             </div>
        </div>
      </section>
      <section class="content">
          <div class="row">
               <div class="col-xs-12">
                 <div class="box">
                  <div class="box-header">
                       <h3 class="box-title">Menu List</h3>
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
                         <th>ฺId</th>
                         <th>Menu Group name</th>
                         <th class="text-center">Actions</th>
                       </tr>
                       <?php
                       if(!empty($menuRecords))
                       {
                           foreach($menuRecords as $record)
                           {

                       ?>
                       <tr>
                         <td><?php echo $record->id ?></td>
                         <td><?php echo $record->name ?></td>
                         <td class="text-center">

                             <a class="btn btn-sm btn-info" href="<?php echo base_url().'editMunuOld/'.$record->id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
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
