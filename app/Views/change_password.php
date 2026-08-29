<?php /** @var bool $changed */ ?>
<div class="background-form" style="background-image: url(<?= base_url('assets/images/bg-form.png') ?>);"></div>
<div class="content-form">
    <section class="content-header">
        <h1>Change Password <small>Set new password for your account</small></h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-4">
                <div class="box box-primary">
                    <div class="box-header"><h3 class="box-title">Enter Details</h3></div>
                    <form role="form" action="<?= base_url('changePassword') ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="box-body">
                            <div class="form-group">
                                <label for="inputOldPassword">Old Password</label>
                                <input type="password" class="form-control" id="inputOldPassword" placeholder="Old password" name="oldPassword" maxlength="128" required>
                            </div>
                            <hr>
                            <div class="form-group">
                                <label for="inputPassword1">New Password</label>
                                <input type="password" class="form-control" id="inputPassword1" placeholder="New password" name="newPassword" minlength="12" maxlength="128" required>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword2">Confirm New Password</label>
                                <input type="password" class="form-control" id="inputPassword2" placeholder="Confirm new password" name="cNewPassword" minlength="12" maxlength="128" required>
                            </div>
                        </div>
                        <div class="box-footer">
                            <input type="submit" class="btn btn-primary" value="Submit">
                            <input type="reset" class="btn btn-default" value="Reset">
                        </div>
                    </form>
                </div>
            </div>
            <?php if ($changed): ?>
                <div class="col-md-4"><div class="alert alert-success alert-dismissable" role="status">Password changed</div></div>
            <?php endif ?>
        </div>
    </section>
</div>
