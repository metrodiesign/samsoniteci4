<!DOCTYPE html>

<html>

  <head>

    <meta charset="UTF-8">

    <title>Tracking | Admin System Log in</title>

    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <link href="<?php echo base_url(); ?>assets/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />

    <link href="<?php echo base_url(); ?>assets/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css" />

    <link href="<?php echo base_url(); ?>assets/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />

    <link href="<?php echo base_url(); ?>assets/dist/css/CustomAdmin.css" rel="stylesheet" type="text/css" />

    <link href="<?php echo base_url(); ?>assets/css/main.css" rel="stylesheet" type="text/css" />

  </head>

  <body class="login-page">

     <div class="banner-cms">

       <div class="background-img" style="background-image: url(<?php echo base_url(); ?>assets/images/bg-login.jpg)"></div>

     </div>

    <div class="login-box">

      <div class="login-logo">

        <a href="#"><b>Tracking</b></a>

      </div><!-- /.login-logo -->

      <div class="login-box-body">

        <!-- <p class="login-box-msg">Sign In</p> -->

        <?php $this->load->helper('form'); ?>

        <div class="row">

            <div class="col-md-12">

                <?php echo validation_errors('<div class="alert alert-danger alert-dismissable">', ' <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>'); ?>

            </div>

        </div>

        <?php

        $this->load->helper('form');

        $error = $this->session->flashdata('error');

        if($error)

        {

            ?>

            <div class="alert alert-danger alert-dismissable">

                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>

                <?php echo $error; ?>

            </div>

        <?php }

        $success = $this->session->flashdata('success');

        if($success)

        {

            ?>

            <div class="alert alert-success alert-dismissable">

                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>

                <?php echo $success; ?>

            </div>

        <?php } ?>



        <form action="<?php echo base_url(); ?>loginMe" method="post">

          <div class="form-group has-feedback">

             <label for="">USERNAME</label>

            <input type="text" class="form-control" placeholder="UserID" name="username" required />

            <span class="glyphicon glyphicon-envelope form-control-feedback"></span>

          </div>

          <div class="form-group has-feedback">

             <label for="">PASSWORD</label>

            <input type="password" class="form-control" placeholder="Password" name="password" required />

            <span class="glyphicon glyphicon-lock form-control-feedback"></span>

          </div>

          <div class="row">

            <div class="col-xs-7">

              <!-- <div class="checkbox icheck">

                <label>

                  <input type="checkbox"> Remember Me

                </label>

              </div>  -->

              <a href="<?php echo base_url() ?>forgotPassword">Forgot Password</a><br>

            </div><!-- /.col -->

            <div class="col-xs-5" style="text-align: right;">

              <input type="submit" class="btn btn-primary custom-btn" value="Sign In" />

            </div><!-- /.col -->

          </div>

        </form>







      </div><!-- /.login-box-body -->

    </div><!-- /.login-box -->



    <script src="<?php echo base_url(); ?>assets/js/jQuery-2.1.4.min.js"></script>

    <script src="<?php echo base_url(); ?>assets/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>

    <section id="footer">

      <div class="bg-footer">

        <img class="" src="<?php echo base_url(); ?>assets/images/img-footer.png">

        <div class="txt-cen-footer">NEED HELP ? CALL OUR CUSTOMER CENTRE AT</div>

        <div class="txt-num">02-761-9999</div>

      </div>



      <!-- <div class="txt-footer-nm">The Samsonite Official Online Store accepts</div>

      <div class="line"></div>

      <div class="txt-footer-nm">Copyright &copy; 2017 Samsonite. All rights reserved.</div>

      <div class="txt-footer-nm">User Agreement | Privacy Policy | Personal Information Collection Statement | Sitemap</div> -->



    </section>

  </body>



</html>

