
<section id="rs-track">

  <div class="banner-control">
    <img class="rs-bg-size" src="<?php echo base_url(); ?>uploads/web/trackstatus_laptop.png">
  </div>

  <div class="container">
    <div class="row">
      <div class="con-pro-bar">

        <?php
        if(!empty($OrdersRecords))
        {
        $i=0;$tottal=count($OrdersRecords)-1;
            foreach ($OrdersRecords as $uf)
            {
              $action_id=$uf->action_id;
              $update_id=$uf->update_id;
            //  if($action_id){
                 $data_satatus=$uf->status_name_th;
              //}else{
              //    $data_satatus=$uf->description_th;
              //}
              if($uf->status_name=="complete"){
                $c="circle-awe bg-success circle-awe-animate";
              $class_i='<i class="fa fa-check ico-size"></i>';
              }else{
                if($i==0){
                  $c="circle-awe circle-awe-animate";
                  $class_i='';
                }else{
                  $c="circle-awe bg-pass";
                  $class_i='';
                }
             }
             $requestDate=$uf->s_date;
             $dd=$requestDate;
             $AA=substr($dd,0,4);
             $BB=substr($dd,5,2);
             $CC=substr($dd,8,2);
             $DD=$AA+543;
             $data_requestDate=$CC."/".$BB."/".$DD;
        ?>
        <div class="con-step-pass">
          <div class="contain-process">
            <div class="<?php echo $c;?>"></div>
            <?php if($i !=$tottal)
            {
              ?>
              <div class="line-normal line-progress"></div>
              <?php
            }
            ?>
          </div>
          <div class="txt-normal"><?php echo $data_satatus." ".$data_requestDate;?></div>
        </div>
        <?php
        $i++;
            }
        }else{
        ?>
        <div class="con-step-pass">
          <div class="circle-awe bg-unpass">
            <div class="line-normal"></div>
          </div>
          <div class="txt-normal">ไม่มีสินค้า</div>
        </div>
        <?php
        }

         ?>


      </div>
    </div>
  </div>

<!-- </section> -->
