<?php
/**
 * CI3 source: application/views/dashboard.php @ ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6
 * Integration changes are limited to supplied view-model values, escaped flash text and CI4 URL helpers.
 *
 * @var int $GroupID
 * @var int $day_job_newover
 * @var string $successMessage
 */
?>
<div class="content-wrapper">
   <div class="content-dashbord">
      <?php if ($GroupID === 2): ?>
      <section class="content-header">
       <h1>
          <i class="fa fa-tachometer" aria-hidden="true"></i> Dashboard
          <small>Control panel</small>
       </h1>
      </section>
      <section class="content">
          <div class="row">
              <div class="col-sm-12 col-lg-4 col-md-4 ">
                <a href="<?= esc(base_url('ReportTrackingListing')) ?>" class="small-box-footer">
                <div class="small-box bg-blue">
                  <div class="inner">
                    <h2>REPORTS</h2>
                  </div>
                  <div class="icon">
                    <i class="ion ion-bag"></i>
                  </div>
                </div>
              </a>
              </div>
          </div>
      </section>
      <?php elseif ($GroupID === 3): ?>
    <section class="content">
    <div class="row">
      <div class="col-sm-12 col-lg-4 col-md-4 ">
        <a href="<?= esc(base_url('UploadexcelListing')) ?>" class="small-box-footer">
        <div class="small-box bg-light-blue">
          <div class="inner">
            <h2>UPLOAD STATUS</h2>
          </div>
          <div class="icon">
            <i class="ion ion-bag"></i>
          </div>
        </div>
      </a>
      </div>
      <div class="col-sm-12 col-lg-4 col-md-4 ">
        <a href="<?= esc(base_url('UploadneworderexcelListing')) ?>" class="small-box-footer">
        <div class="small-box bg-light-blue">
          <div class="inner">
            <h2>UPLOAD CMG DATA </h2>
          </div>
          <div class="icon">
            <i class="ion ion-stats-bars"></i>
          </div>
        </div>
      </a>
      </div>
    </div>
</section>
<section class="content-header">
 <h1>
    <i class="fa fa-tachometer" aria-hidden="true"></i> Dashboard
    <small>Control panel</small>
 </h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-sm-12 col-lg-4 col-md-4 ">
          <a href="<?= esc(base_url('ReportTrackingListing')) ?>" class="small-box-footer">
          <div class="small-box bg-blue">
            <div class="inner">
              <h2>REPORTS</h2>
            </div>
            <div class="icon">
              <i class="ion ion-bag"></i>
            </div>
          </div>
        </a>
        </div>
    </div>
</section>
    <?php elseif ($GroupID === 4): ?>
    <?php if ($day_job_newover > 0): ?>
    <div class="modal fade in" id="myModal" tabindex="-1" role="dialog" aria-labelledby="exampleModal" aria-hidden="true" style="display: block; padding-right: 16px;">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-body">
          <h3 style="text-align: left; line-height: 1; font-size: 1.5em; color: #7b7b7b;">กรุณาตรวจสอบ New order ที่ยังค้างส่ง CMG ในขั้นตอน Logistics</h3>
           <div class="" style="text-align: left; line-height: 1; font-size: 1.2em; color: #7b7b7b;">
              <?= esc($successMessage) ?>
           </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn custom-btn" data-dismiss="modal" id="close">ตกลง</button>
        </div>
      </div>
    </div>
    </div>
    <div class="alert alert-success alert-dismissable">
         <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
    </div>
    <script type="text/javascript">
    $('#close').click(function() {
      window.location.href = <?= json_encode(base_url('ordersListing'), JSON_UNESCAPED_SLASHES) ?>;
    });
    </script>
    <?php endif ?>
    <section class="content">
        <div class="row">
            <div class="col-sm-12 col-lg-5 col-md-6 ">
              <a href="<?= esc(base_url('ordersListing')) ?>" class="small-box-footer">
              <div class="small-box bg-light-blue">
                <div class="inner">
                  <h2>1. NEW REQUEST REPAIR</h2>
                </div>
                <div class="icon">
                  <i class="ion ion-bag"></i>
                </div>
              </div>
            </a>
            </div>
            <div class="col-sm-12 col-lg-5 col-md-6 ">
              <a href="<?= esc(base_url('sendorderListing')) ?>" class="small-box-footer">
              <div class="small-box bg-light-blue">
                <div class="inner">
                  <h2>2. LOGISTICS</h2>
                </div>
                <div class="icon">
                  <i class="ion ion-stats-bars"></i>
                </div>
              </div>
            </a>
            </div>
            <div class="col-sm-12 col-lg-5 col-md-6 ">
              <a href="<?= esc(base_url('TrackingreturnListing')) ?>" class="small-box-footer">
              <div class="small-box bg-light-blue">
                <div class="inner">
                  <h2>3. DELIVER TO CUSTOMER</h2>
                </div>
                <div class="icon">
                  <i class="ion ion-pie-graph"></i>
                </div>
              </div>
            </a>
            </div>
            <div class="col-sm-12 col-lg-5 col-md-6 ">
              <a href="<?= esc(base_url('TrackingcompleteListing')) ?>" class="small-box-footer">
              <div class="small-box bg-light-blue">
                <div class="inner">
                  <h2>4. COMPLETE FEEDBACK</h2>
                </div>
                <div class="icon">
                  <i class="ion ion-pie-graph"></i>
                </div>
              </div>
              </a>
            </div>
          </div>
    </section>
    <section class="content-header">
     <h1>
        <i class="fa fa-tachometer" aria-hidden="true"></i> Dashboard
        <small>Control panel</small>
     </h1>
    </section>
    <section class="content">
      <div class="row">
         <div class="col-sm-12 col-lg-4 col-md-4 ">
           <a href="<?= esc(base_url('ReportTrackingListing')) ?>" class="small-box-footer">
           <div class="small-box bg-blue">
             <div class="inner">
               <h2>REPORTS</h2>
             </div>
             <div class="icon">
               <i class="ion ion-bag"></i>
             </div>
           </div>
         </a>
         </div>
      </div>
    </section>
    <?php else: ?>
       <section class="content-header">
        <h1>
           <i class="fa fa-tachometer" aria-hidden="true"></i> Dashboard
           <small>Control panel</small>
        </h1>
       </section>
      <section class="content">
          <div class="row">
              <div class="col-sm-12 col-lg-4 col-md-4 ">
                <a href="<?= esc(base_url('ReportTrackingListing')) ?>" class="small-box-footer">
                <div class="small-box bg-blue">
                  <div class="inner">
                    <h2>REPORTS</h2>
                  </div>
                  <div class="icon">
                    <i class="ion ion-bag"></i>
                  </div>
                </div>
              </a>
              </div>
            </div>
      </section>
      <?php endif ?>
   </div>
</div>
