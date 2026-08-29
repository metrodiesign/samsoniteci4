<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Tracking | Admin System Log in</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link href="<?= base_url('assets/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet" type="text/css" />
    <link href="<?= base_url('assets/font-awesome/css/font-awesome.css') ?>" rel="stylesheet" type="text/css" />
    <link href="<?= base_url('assets/dist/css/AdminLTE.min.css') ?>" rel="stylesheet" type="text/css" />
    <link href="<?= base_url('assets/dist/css/CustomAdmin.css') ?>" rel="stylesheet" type="text/css" />
    <link href="<?= base_url('assets/css/main.css') ?>" rel="stylesheet" type="text/css" />
  </head>
  <body class="login-page">
    <div class="banner-cms">
      <div class="background-img" style="background-image: url(<?= base_url('assets/images/bg-login.jpg') ?>)"></div>
    </div>
    <div class="login-box">
      <div class="login-logo">
        <a href="#"><b>Tracking</b></a>
      </div><!-- /.login-logo -->
      <div class="login-box-body">
        <!-- <p class="login-box-msg">Sign In</p> -->
        <div class="row">
          <div class="col-md-12"></div>
        </div>
        <?php if (is_string($error) && $error !== ''): ?>
          <div class="alert alert-danger alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <?= esc($error) ?>
          </div>
        <?php endif ?>
        <?php if (is_string($success) && $success !== ''): ?>
          <div class="alert alert-success alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <?= esc($success) ?>
          </div>
        <?php endif ?>

        <form action="<?= base_url('loginMe') ?>" method="post">
          <?= csrf_field() ?>
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
              <a href="<?= base_url('forgotPassword') ?>">Forgot Password</a><br>
            </div><!-- /.col -->
            <div class="col-xs-5" style="text-align: right;">
              <input type="submit" class="btn btn-primary custom-btn" value="Sign In" />
            </div><!-- /.col -->
          </div>
        </form>
      </div><!-- /.login-box-body -->
    </div><!-- /.login-box -->

    <script src="<?= base_url('assets/js/jQuery-2.1.4.min.js') ?>"></script>
    <script src="<?= base_url('assets/bootstrap/js/bootstrap.min.js') ?>" type="text/javascript"></script>
    <section id="footer">
      <div class="bg-footer">
        <img class="" src="<?= base_url('assets/images/img-footer.png') ?>">
        <div class="txt-cen-footer">NEED HELP ? CALL OUR CUSTOMER CENTRE AT</div>
        <div class="txt-num">02-761-9999</div>
      </div>
    </section>
  </body>
</html>
