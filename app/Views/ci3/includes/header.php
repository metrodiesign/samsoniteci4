<?php
$GroupID="";
$GroupID=$this->session->userdata('GroupID');
$BranchID="";
$BranchID=$this->session->userdata('BranchID');
//echo $GroupID."55555555";
//getMenoGroup($group_id)
 ?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <!-- Bootstrap 3.3.4 -->
    <link href="<?php echo base_url(); ?>assets/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- AdminLTE Skins. Choose a skin from the css/skins folder instead of downloading all of them to reduce the load. -->
    <link href="//cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="//cdn.datatables.net/fixedcolumns/3.2.4/css/fixedColumns.dataTables.min.css" rel="stylesheet">
    <!-- FontAwesome 4.3.0 -->
    <link href="<?php echo base_url(); ?>assets/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/dist/css/CustomAdmin.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/css/main.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/css/multifreezer.css" rel="stylesheet" type="text/css" />

    <link href="<?php echo base_url(); ?>assets/dist/css/skins/_all-skins.min.css" rel="stylesheet" type="text/css" />


    <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/jquerydatepicker/jquery-1.10.2.min.js"></script>

      <link rel="stylesheet" media="all" type="text/css" href="<?php echo base_url(); ?>assets/js/jquerydatepicker/jquery-ui.css" />
    <link rel="stylesheet" media="all" type="text/css" href="<?php echo base_url(); ?>assets/js/jquerydatepicker/jquery-ui-timepicker-addon.css" />

    <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/jquerydatepicker/jquery-ui.min.js"></script>

    <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/jquerydatepicker/jquery-ui-timepicker-addon.js"></script>
    <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/jquerydatepicker/jquery-ui-sliderAccess.js"></script>
    <style>
    	.error{
    		color:red;
    		font-weight: normal;
    	}
    </style>
    <!-- jQuery 2.1.4 -->

    <script type="text/javascript">
        var baseURL = "<?php echo base_url(); ?>";
    </script>

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    
  </head>
  <body class="skin-blue sidebar-mini">
    <div class="wrapper">

      <header class="main-header">
        <!-- Logo -->
        <a href="<?php echo base_url(); ?>" class="logo" style="padding: 0; background-color: #fff; color: #014c8f; border-right: 1px solid #014c8f;">
          <!-- logo for regular state and mobile devices -->
          <img class="img-responsive" src="<?php echo base_url(); ?>assets/images/print-logo.jpg" alt="">
          <!-- <span class="logo-lg">
             <img src="<?php echo base_url(); ?>assets/images/cms-logo.png" alt="">
          </span> -->
        </a>

        <!-- Header Navbar: style can be found in header.less -->

        <nav class="navbar navbar-static-top" role="navigation" style="background-color: #fff; color: #014c8f; border-bottom: 1px solid #014c8f;">
          <!-- Sidebar toggle button-->
          <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button" style="color: #000;">
            <span class="sr-only">Toggle navigation</span>
          </a>
          <button class="btn btn-sm btn-default" onclick="history.back(-1)">Back</button>
          <?php
          if($BranchID){
            $BranchName=$this->user_model->getbransName($BranchID);
            ?>
          <div class="logo" style="background-color: #fff; color: #014c8f;">
               <span class="logo-lg"><b>BRANCH <?php echo $BranchName;?></b></span>
          </div>
          <?php
        }
         ?>
          <div class="navbar-custom-menu">
            <ul class="nav navbar-nav">
              <li class="dropdown tasks-menu">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                  <i class="fa fa-history"></i>
                </a>
                <ul class="dropdown-menu">
                  <li class="header"> Last Login : <i class="fa fa-clock-o"></i> <?= empty($last_login) ? "First Time Login" : $last_login; ?></li>
                </ul>
              </li>
              <?php
                if($GroupID <=3)
                {
                $menu_getbrans=$this->user_model->getbrans();
               ?>
              <li class="dropdown tasks-menu">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                  <i class="fa fa-university"></i>
                </a>
                <ul class="dropdown-menu">
                  <?php
                  if(!empty($menu_getbrans))
                  {
                    $response="[";
                      foreach($menu_getbrans as $menu_getbrans_record)
                      {

                            $menu_branch_id=$menu_getbrans_record->branch_id;
                            $menu_branch_name=$menu_getbrans_record->branch_name;
                            if($response !="[")$response.=",";
                            $response.='{"label":"'.$menu_branch_name.'","value":"'.base_url().'ReportTrackingListing/0/'.$menu_branch_id.'"}';

                      }
                          $response.="]";
                    }

                   ?>
                   <script>

                   $(document).ready(function() {

                     var xsource = <?php echo $response;?>;

                       $("input#autocomplete").autocomplete({
                           source: xsource,
                           select: function( event, ui ) {
                               window.location.href = ui.item.value;
                           }
                       });
                   });
                   </script>
                   <input id="autocomplete" class="form-control" placeholder="Search"  />

                </ul>
              </li>
              <?php
                }
               ?>
              <!-- User Account: style can be found in dropdown.less -->
              <li class="dropdown user user-menu">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                  <img src="<?php echo base_url(); ?>assets/dist/img/avatar.png" class="user-image" alt="User Image"/>
                  <span class="hidden-xs"><?php echo $name; ?></span>
                </a>
                <ul class="dropdown-menu">
                  <!-- User image -->
                  <li class="user-header">
                    <img src="<?php echo base_url(); ?>assets/dist/img/avatar.png" class="img-circle" alt="User Image" />
                    <p>
                      <?php echo $name; ?>
                      <small><?php echo $role_text; ?></small>
                    </p>
                  </li>
                  <!-- Menu Footer-->
                  <li class="user-footer">
                    <div class="pull-right">
                      <a href="<?php echo base_url(); ?>loadChangePass" class="btn btn-default btn-flat"><i class="fa fa-key"></i> Change Password</a>
                    </div>

                  </li>
                </ul>
              </li>
              <li class="btn-flat">
                <a href="<?php echo base_url(); ?>logout" ><i class="fa fa-sign-out"></i> Sign out</a>

              </li>
            </ul>
          </div>
        </nav>
      </header>
      <!-- Left side column. contains the logo and sidebar -->
      <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <section class="sidebar">
          <!-- sidebar menu: : style can be found in sidebar.less -->
          <ul class="sidebar-menu">
            <li class="header">MENU</li>


            <?php
            $menu=$this->user_model->getMenoGroup($GroupID);
          //  $array=( isset($menu[0]['group_type']) ) ? explode(",", $menu[0]['group_type']) : array(0);
          //var_dump($array);
            if(!empty($menu))
            {
                foreach($menu as $m_record)
                {
                  $group_type=$m_record->group_type;
                  $array=( isset($group_type) ) ? explode(",", $group_type) : array(0);

                  foreach ($array as $data_group_type) {
                      //echo $group_type."666";
                       $group_menu=$this->user_model->getMeno($data_group_type);
                       $group_name=$this->user_model->getMenoGroupType($data_group_type);
                       $group_icon=$this->user_model->getMenoGroupTypeIcon($data_group_type);
            ?>
              <li class="treeview active">
                            <a href="#"><i class="<?php echo $group_icon;?>"></i><span><?php echo $group_name;?></span>
                              <span class="pull-right-container">
                                <i class="fa fa-angle-left pull-right"></i>
                              </span>
                            </a>
            <?php

                            $i=0;$xi=0;
                            if(!empty($group_menu))
                            {
            ?>
                              <ul class="treeview-menu" >
                            <?php
                                foreach($group_menu as $mu_record)
                                {

                                  if($data_group_type=="3")
                                  {
                                    $i++;
                                  //  echo $BranchID."666";
                                    if($BranchID){
                                      if($i !=3 and $i!=4){
                                        $xi++;
                                        $data_i=$xi.". ";
                                        echo '<li><a href="'.base_url().$mu_record->menu_link.'">'.$data_i.$mu_record->menu_name.'</a></li>';
                                      }

                                    }else{
                                      $data_i=$i.". ";
                                      echo '<li><a href="'.base_url().$mu_record->menu_link.'">'.$data_i.$mu_record->menu_name.'</a></li>';
                                    }
          ?>

          <?php
                                  }else{

          ?>
                    <li><a href="<?php echo base_url().$mu_record->menu_link; ?>"><?php echo $mu_record->menu_name; ?></a></li>
          <?php
                                  }

            ?>

            <?php

                                }
            ?>
                                </ul>
            <?php
                              }
                      }
              ?>
                </li>
              <?php
                  }

              }
           ?>

          </ul>
        </section>
        <!-- /.sidebar -->

      </aside>
      <!-- Left side column. contains the logo and sidebar -->
